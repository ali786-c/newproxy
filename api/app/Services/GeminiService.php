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
                'maxOutputTokens' => 4096,
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
     * Generate a featured image via Gemini Nano Banana (Gemini 3.1 Flash Image).
     */
    public function generateFeaturedImage(string $imagePrompt)
    {
        $this->getFreshConfig();
        
        $imageModel = "gemini-3.1-flash-image-preview"; // Nano Banana 2
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$imageModel}:generateContent?key={$this->apiKey}";

        $response = Http::withoutVerifying()->timeout(120)->post($url, [
            'contents' => [
                ['parts' => [['text' => $imagePrompt]]]
            ]
        ]);

        if ($response->failed()) {
            Log::warning('Gemini Nano Banana API Failed', ['body' => $response->body()]);
            return null; // Fallback handled in orchestrator
        }

        $result = $response->json();

        try {
            // Nano Banana returns image in parts[0]['inlineData']['data']
            $base64Image = $result['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
            if (!$base64Image) {
                Log::warning('No image data in Gemini response', ['response' => $result]);
                return null;
            }

            return $this->saveImageLocally($base64Image);
        } catch (\Exception $e) {
            Log::error('Failed to save Gemini Image', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
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
   - You must generate a highly detailed, professional prompt for an AI image generator.
   - Quality: Include terms like "hyper-realistic", "8k resolution", "highly detailed textures", "cinematic lighting".
   - Diversity: For every blog, choose a UNIQUE visual style (e.g., Minimalist 3D, Cyberpunk, Corporate Memphis - but refined, Macro Photography, Neomorphism, or High-End Tech Noir).
   - Composition: Specify camera angle (e.g., "wide shot", "top-down", "close-up macro") and depth of field ("bokeh background").
   - NO TEXT: The image must NOT contain any text, letters, or words.
   - No People: Focus on abstract tech, servers, digital networks, or hardware.

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
  "image_prompt": "A [Visual Style] of [Specific Subject] relating to {$keyword}. Composition: [Angle]. Lighting: [Lighting Type]. Extra detail: [Specific visual element]. Quality: hyper-realistic, 8k, photorealistic textures. No text."
}
PROMPT;
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
     * Save base64 image to local public storage.
     */
    protected function saveImageLocally(string $base64Data): string
    {
        $imageData = base64_decode($base64Data);
        $fileName = 'blog_' . Str::random(10) . '_' . time() . '.jpg';
        $path = 'blog/' . $fileName;

        Storage::disk('public')->put($path, $imageData);

        return Storage::url($path);
    }
}
