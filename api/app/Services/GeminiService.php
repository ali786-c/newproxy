<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeminiService
{
    protected $apiKey;
    protected $model;

    public function __construct()
    {
        // Pulled fresh in methods
    }

    protected function getFreshConfig()
    {
        $dbApiKey = Setting::getValue('gemini_api_key');
        $this->apiKey = ($dbApiKey && str_starts_with($dbApiKey, 'AIza')) ? $dbApiKey : config('services.gemini.key');
        
        $this->model = Setting::getValue('gemini_model') ?: config('services.gemini.model', 'gemini-2.5-flash');
    }

    /**
     * Generate blog post data as structured JSON.
     */
    public function generateBlogPost(string $keyword, string $category = null, array $recentTitles = [])
    {
        $this->getFreshConfig();

        if (!$this->apiKey) {
            throw new \Exception('Gemini API key is not configured.');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $prompt = $this->buildBlogPrompt($keyword, $category, $recentTitles);

        $response = Http::withoutVerifying()->timeout(90)->post($url, [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.8,
                'maxOutputTokens' => 8192,
                'responseMimeType' => 'application/json',
            ],
        ]);

        if ($response->failed()) {
            throw new \Exception('Gemini API Error: ' . $response->body());
        }

        $result = $response->json();
        
        try {
            $rawJson = $result['candidates'][0]['content']['parts'][0]['text'];
            return $this->extractAndValidateJson($rawJson);
        } catch (\Exception $e) {
            Log::error('Gemini Blog Extraction Failed', ['error' => $e->getMessage(), 'raw' => $result]);
            throw new \Exception('Failed to extract structured blog data: ' . $e->getMessage());
        }
    }

    /**
     * Generate a professional image brief from blog content to ensure relevance.
     */
    public function generateImageBrief(array $blogData): ?array
    {
        $this->getFreshConfig();
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $prompt = $this->buildImageBriefPrompt($blogData);

        $response = Http::withoutVerifying()->timeout(60)->post($url, [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'responseMimeType' => 'application/json',
            ],
        ]);

        if ($response->failed()) {
            Log::warning('Gemini Image Brief API Failed', ['body' => $response->body()]);
            return null;
        }

        $result = $response->json();

        try {
            $rawJson = $result['candidates'][0]['content']['parts'][0]['text'];
            return json_decode($rawJson, true);
        } catch (\Exception $e) {
            Log::error('Failed to parse Image Brief JSON', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Compose final prompt and generate a professional featured image via Gemini 3 Pro.
     */
    public function generateFeaturedImage(array $brief)
    {
        $this->getFreshConfig();
        
        $imagePrompt = $this->composeFinalImagePrompt($brief);
        $imageModel = "gemini-3-pro-image-preview"; 
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$imageModel}:generateContent?key={$this->apiKey}";

        $response = Http::withoutVerifying()->timeout(150)->post($url, [
            'contents' => [
                ['parts' => [['text' => $imagePrompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'imageConfig' => [
                    'aspectRatio' => '16:9',
                    'imageSize' => '2K',
                ],
            ],
        ]);

        if ($response->failed()) {
            Log::warning('Gemini Pro Image API Failed', ['body' => $response->body()]);
            return null;
        }

        $result = $response->json();

        try {
            $imageInfo = $this->extractInlineImage($result);
            if (!$imageInfo) {
                Log::warning('No image data in Gemini Pro response', ['response' => $result]);
                return null;
            }

            return $this->saveImageLocally($imageInfo['data'], $imageInfo['mimeType']);
        } catch (\Exception $e) {
            Log::error('Failed to save Gemini Pro Image', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Blog content generation instructions.
     */
    protected function buildBlogPrompt(string $keyword, string $category, array $recentTitles): string
    {
        $categoryStr = $category ? " within the category '{$category}'" : "";
        $recentStr = !empty($recentTitles) ? "DO NOT use or resemble these recent titles: " . implode(', ', $recentTitles) : "";
        
        return <<<PROMPT
Act as a professional technical writer and SEO expert for a global proxy provider.
Task: Write a high-quality, engaging blog post about "{$keyword}"{$categoryStr}.

RULES:
1. Tone: Premium, professional, yet conversational. High engagement.
2. Word Count: 600-800 words.
3. Title Diversity: 
   - Patterns: Listicle, Question-based, "Secrets" reveal, Case Study, or Myth-busting.
   - {$recentStr}
4. SEO: Naturally include "{$keyword}" in the first 100 words and at least one H2.
5. Structure: Hook, Lead, 3-5 Body Sections with headings, Takeaways, FAQs, and CTA.
6. NO MARKDOWN: Do NOT use markdown symbols like **bold** or _italic_ in the text. Write raw text only.

OUTPUT FORMAT (Strict JSON):
{
  "title": "Unconventional click-worthy title",
  "excerpt": "Compelling 2-sentence summary",
  "hook": "Strong opening sentence",
  "intro": "1 paragraph introduction",
  "sections": [
    { "heading": "Heading Name", "content": "Paragraph content" }
  ],
  "takeaways": ["Point 1", "Point 2", "Point 3"],
  "faqs": [ { "q": "Question?", "a": "Answer text." } ],
  "cta": "Call to action text"
}
PROMPT;
    }

    /**
     * Image brief generation instructions - focusing on literal relevance.
     */
    protected function buildImageBriefPrompt(array $blogData): string
    {
        $title = $blogData['title'] ?? '';
        $intro = $blogData['intro'] ?? '';

        return <<<PROMPT
You are a professional art director for a technology SaaS blog.
Task: Create a literal, concrete visual brief for a featured image based on the blog content.

BLOG TITLE: {$title}
BLOG INTRO: {$intro}

RULES (CRITICAL):
1. The image must visually match the article topic LITERALLY and DIRECTLY.
2. Avoid abstract metaphors, floating geometric shapes, glowing spheres, or futuristic sculptures.
3. Subject: Realistic enterprise technology scenes, server infrastructure, network equipment, high-end office environments, dashboards, or data centers.
4. Mood: Professional, clean, modern, high-end corporate tech.
5. Negative: No text, no logos, no watermarks, no people.

OUTPUT FORMAT (Strict JSON):
{
  "subject": "Clear description of the main literal subject matter",
  "environment": "Description of the setting/background (e.g., modern datacenter, corporate office)",
  "supporting_elements": ["Element 1", "Element 2"],
  "style": "realistic editorial technology photography",
  "composition": "wide 16:9 hero banner with cinematic framing",
  "lighting": "professional studio lighting with sharp detail"
}
PROMPT;
    }

    /**
     * Assemble the final high-fidelity prompt.
     */
    protected function composeFinalImagePrompt(array $brief): string
    {
        $elements = implode(', ', $brief['supporting_elements'] ?? []);
        return sprintf(
            '%s in a %s. Supporting elements: %s. Style: %s. Composition: %s. Lighting: %s. Quality: 8k, hyper-realistic, photorealistic textures. NO TEXT, NO LOGOS, NO WATERMARKS.',
            $brief['subject'] ?? 'Professional technology infrastructure',
            $brief['environment'] ?? 'clean datacenter environment',
            $elements ?: 'none',
            $brief['style'] ?? 'realistic editorial technology photography',
            $brief['composition'] ?? 'wide 16:9 banner',
            $brief['lighting'] ?? 'professional studio lighting'
        );
    }

    /**
     * Extract inline image data from the response.
     */
    protected function extractInlineImage(array $result): ?array
    {
        $parts = $result['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (!empty($part['inlineData']['data'])) {
                return [
                    'data' => $part['inlineData']['data'],
                    'mimeType' => $part['inlineData']['mimeType'] ?? 'image/jpeg'
                ];
            }
        }
        return null;
    }

    /**
     * Extract and validate JSON.
     */
    protected function extractAndValidateJson(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $data = json_decode($matches[0], true);
        } else {
            $data = json_decode($text, true);
        }

        if (!$data || !isset($data['title'], $data['sections'])) {
            throw new \Exception('Invalid JSON structure from AI.');
        }

        return $data;
    }

    /**
     * Save base64 image locally.
     */
    protected function saveImageLocally(string $base64Data, string $mimeType = 'image/jpeg'): string
    {
        $imageData = base64_decode($base64Data);
        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $fileName = 'blog_' . Str::random(10) . '_' . time() . '.' . $extension;
        $path = 'blog/' . $fileName;

        Storage::disk('public')->put($path, $imageData);

        $url = Storage::disk('public')->url($path);
        
        // Convert to site-root relative URL (e.g., /api/storage/...) for better compatibility
        return parse_url($url, PHP_URL_PATH);
    }
}
