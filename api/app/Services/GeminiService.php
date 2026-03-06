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

        $prompt = $this->buildPrompt($keyword, $category, $recentTitles);

        $response = Http::withoutVerifying()->timeout(90)->post($url, [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.8,
                'maxOutputTokens' => 8192, // Increased to handle reasoning/thoughts
                'responseMimeType' => 'application/json',
            ]
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
     * Generate a professional featured image via Gemini 3 Pro Image.
     */
    public function generateFeaturedImage(string $imagePrompt)
    {
        $this->getFreshConfig();
        
        // Upgrade to Pro model for professional asset production
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
                    'imageSize' => '2K', // Higher fidelity for premium look
                ],
            ],
        ]);

        if ($response->failed()) {
            Log::warning('Gemini Pro Image API Failed', ['body' => $response->body()]);
            return null;
        }

        $result = $response->json();

        try {
            // Safer extraction: loop through parts to find inlineData
            $imageInfo = $this->extractInlineImage($result);
            if (!$imageInfo) {
                Log::warning('No image data in Gemini Pro response', ['response' => $result]);
                return null;
            }

            return $this->saveImageLocally($imageInfo['data'], $imageInfo['mimeType']);
        } catch (\Exception $e) {
            Log::error('Failed to save Gemini Pro Image', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return null;
        }
    }

    /**
     * Build the structured prompt for diverse blog content.
     */
    protected function buildPrompt(string $keyword, string $category, array $recentTitles): string
    {
        $categoryStr = $category ? " within the category '{$category}'" : "";
        $recentStr = !empty($recentTitles) ? "DO NOT use or resemble these recent titles: " . implode(', ', $recentTitles) : "";
        
        return <<<PROMPT
Act as a professional technical writer and SEO expert for a global proxy provider.
Task: Write a high-quality, engaging blog post about "{$keyword}"{$categoryStr}.

RULES:
1. Tone: Premium, professional, yet conversational. High engagement.
2. Word Count: Target 600-700 words. Hard limit: Max 800 words.
3. Title Diversity: 
   - DO NOT start with "What is", "What are", or "Introduction to".
   - Patterns: Listicle, Question-based, "Secrets" reveal, Case Study, or Myth-busting.
   - {$recentStr}
4. SEO: Naturally include "{$keyword}" in the first 100 words and at least one H2.
5. Structure: 
   - Hook: A 1-2 sentence compelling opening.
   - Lead: 1 paragraph intro.
   - Body: 3-5 sections with clear headings.
   - Takeaways: 3-4 bullet points.
   - FAQ: 2-3 common questions and answers.
   - CTA: Concise call to action.

6. Image Generation Prompt (CRITICAL):
   - Generate a premium SaaS blog hero image prompt in English.
   - Visual Style Buckets (Choose ONE unique direction for this post):
     1) Editorial 3D product render (black/graphite materials).
     2) Clean enterprise infrastructure photography style (server hardware macro).
     3) Isometric systems diagram aesthetic (sleek, non-neon).
     4) Minimal abstract glass-and-metal data visualization.
     5) Luxury dark-mode tech composition.
   - Anti-Repetition Rules:
     - DO NOT use glowing spheres, holographic globes, or floating cubes.
     - AVOID generic cyberpunk blue/purple neon circuits.
     - NO random world maps or cityscapes.
   - Composition: Wide cinematic banner style (16:9).
   - Constraints: NO text, NO logos, NO watermarks, NO people.

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
  "faqs": [
    { "q": "Question?", "a": "Answer text." }
  ],
  "cta": "Call to action text",
  "image_prompt": "A [Chosen Visual Bucket Style] of [Specific Concept relating to {$keyword}]. Composition: Wide-angle cinematic. Lighting: Professional studio lighting. Texture: Realistic brushed metal/glass. Quality: 8k, hyper-realistic, no text."
}
PROMPT;
    }

    /**
     * Robust extraction loop for inline image data.
     */
    protected function extractInlineImage(array $result): ?array
    {
        $parts = $result['candidates'][0]['content']['parts'] ?? [];

        foreach ($parts as $part) {
            if (!empty($part['inlineData']['data'])) {
                return [
                    'data' => $part['inlineData']['data'],
                    'mimeType' => $part['inlineData']['mimeType'] ?? 'image/png'
                ];
            }
        }

        return null;
    }

    /**
     * Extract JSON from potentially noisy AI response and validate fields.
     */
    protected function extractAndValidateJson(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $data = json_decode($matches[0], true);
        } else {
            $data = json_decode($text, true);
        }

        if (!$data || !isset($data['title'], $data['sections'], $data['image_prompt'])) {
            throw new \Exception('Invalid JSON structure from AI.');
        }

        return $data;
    }

    /**
     * Save base64 image with proper extension detection.
     */
    protected function saveImageLocally(string $base64Data, string $mimeType = 'image/png'): string
    {
        $imageData = base64_decode($base64Data);
        
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        $fileName = 'blog_' . Str::random(10) . '_' . time() . '.' . $extension;
        $path = 'blog/' . $fileName;

        Storage::disk('public')->put($path, $imageData);

        return Storage::url($path);
    }
}
