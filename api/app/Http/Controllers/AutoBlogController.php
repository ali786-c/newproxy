<?php

namespace App\Http\Controllers;

use App\Models\AutoBlogKeyword;
use App\Models\BlogPost;
use App\Models\Setting;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AutoBlogController extends Controller
{
    /**
     * Admin: List keywords and settings.
     */
    public function index()
    {
        $dbApiKey = Setting::getValue('gemini_api_key') ?? '';
        // A valid Gemini key is typically 39 chars. We use 15 as a safe minimum.
        $apiKey = (strlen($dbApiKey) > 15 && str_starts_with($dbApiKey, 'AIza')) ? $dbApiKey : config('services.gemini.key', '');

        return response()->json([
            'keywords' => AutoBlogKeyword::latest()->get(),
            'settings' => [
                'gemini_api_key' => $apiKey,
                'gemini_model'   => Setting::getValue('gemini_model') ?: config('services.gemini.model', 'gemini-2.5-flash'),
                'auto_posting_enabled' => Setting::getValue('auto_blog_enabled', '0') === '1',
            ]
        ]);
    }

    /**
     * Admin: Add a new keyword.
     */
    public function storeKeyword(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|unique:auto_blog_keywords,keyword|max:255',
            'category' => 'nullable|string|max:100',
        ]);

        $keyword = AutoBlogKeyword::create($request->only('keyword', 'category'));

        \App\Models\AdminLog::log(
            'add_blog_keyword',
            "Added blog keyword: {$keyword->keyword}" . ($keyword->category ? " ({$keyword->category})" : ""),
            null,
            ['keyword' => $keyword->keyword, 'category' => $keyword->category]
        );

        return response()->json($keyword, 201);
    }

    /**
     * Admin: Delete a keyword.
     */
    public function destroyKeyword($id)
    {
        $keyword = AutoBlogKeyword::findOrFail($id);
        $keywordVal = $keyword->keyword;
        $keyword->delete();

        \App\Models\AdminLog::log(
            'delete_blog_keyword',
            "Removed blog keyword: {$keywordVal}",
            null,
            ['id' => $id]
        );

        return response()->json(['message' => 'Keyword deleted.']);
    }

    /**
     * Admin: Update Gemini settings.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'gemini_api_key' => 'nullable|string',
            'gemini_model'   => 'nullable|string',
            'auto_blog_enabled' => 'nullable|boolean',
        ]);

        if ($request->has('gemini_api_key')) {
            \App\Models\Setting::updateOrCreate(['key' => 'gemini_api_key'], ['value' => $request->gemini_api_key]);
        }

        if ($request->has('gemini_model')) {
            \App\Models\Setting::updateOrCreate(['key' => 'gemini_model'], ['value' => $request->gemini_model]);
        }

        if ($request->has('auto_blog_enabled')) {
            \App\Models\Setting::updateOrCreate(['key' => 'auto_blog_enabled'], ['value' => $request->auto_blog_enabled ? '1' : '0']);
        }

        \App\Models\AdminLog::log(
            'update_autoblog_settings',
            "Updated Gemini Auto-Blog Settings",
            null,
            $request->except('gemini_api_key') // Don't log the API key in plain context
        );

        return response()->json(['message' => 'Settings updated.']);
    }

    /**
     * Admin: Manually trigger a blog post generation.
     */
    public function trigger(Request $request, GeminiService $gemini, \App\Services\BlogRenderer $renderer)
    {
        $keywordObj = null;

        if ($request->has('keyword_id')) {
            $keywordObj = AutoBlogKeyword::findOrFail($request->keyword_id);
        } else {
            // Pick least recently used active keyword
            $keywordObj = AutoBlogKeyword::active()
                ->orderBy('last_used_at', 'asc')
                ->first();
        }

        if (!$keywordObj) {
            return response()->json(['message' => 'No active keywords found.'], 400);
        }

        try {
            // 1. Fetch recent titles for diversity
            $recentTitles = BlogPost::latest()->take(10)->pluck('title')->toArray();

            // 2. Generate Structured Content (with retry)
            $blogData = null;
            $maxRetries = 2;
            $attempt = 0;

            while ($attempt < $maxRetries) {
                $attempt++;
                try {
                    $blogData = $gemini->generateBlogPost(
                        $keywordObj->keyword, 
                        $keywordObj->category ?? 'General', 
                        $recentTitles
                    );

                    // Validate word count (heuristic: total chars / 6)
                    $totalContent = '';
                    foreach ($blogData['sections'] as $s) $totalContent .= $s['content'];
                    $wordCount = str_word_count(strip_tags($totalContent));

                    if ($wordCount >= 400 && $wordCount <= 900) break; 
                    
                    \Illuminate\Support\Facades\Log::info("Word count out of range ({$wordCount}), retrying...", ['attempt' => $attempt]);
                } catch (\Exception $e) {
                    if ($attempt >= $maxRetries) throw $e;
                    \Illuminate\Support\Facades\Log::warning("AI Generation attempt {$attempt} failed, retrying...", ['error' => $e->getMessage()]);
                }
            }

            // 3. Generate Featured Image
            $imageUrl = null;
            if (!empty($blogData['image_prompt'])) {
                $imageUrl = $gemini->generateFeaturedImage($blogData['image_prompt']);
            }

            // 4. Render Premium HTML
            $contentHtml = $renderer->render($blogData, $imageUrl);

            // 5. Create the Post
            $post = BlogPost::create([
                'title'     => $blogData['title'],
                'slug'      => Str::slug($blogData['title']) . '-' . Str::random(5),
                'content'   => $contentHtml,
                'excerpt'   => $blogData['excerpt'],
                'category'  => $keywordObj->category ?? 'General',
                'image_url' => $imageUrl,
                'image_prompt' => $blogData['image_prompt'] ?? null,
                'image_source' => $imageUrl ? 'ai_gemini' : 'none',
                'is_draft'  => false,
                'published_at' => now(),
                'author_id' => null,
            ]);

            $keywordObj->update(['last_used_at' => now()]);

            \App\Models\AdminLog::log(
                'trigger_autoblog',
                "Generated AI blog via Nano Banana: '{$post->title}'",
                null,
                ['post_id' => $post->id, 'keyword' => $keywordObj->keyword]
            );

            return response()->json([
                'message' => 'AI blog post generated with custom design and images!',
                'post' => $post
            ]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AI Generation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'AI Generation Failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
