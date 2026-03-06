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
    public function trigger(Request $request, GeminiService $gemini)
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
            $rawResponse = $gemini->generateBlogPost($keywordObj->keyword);

            // Robustly extract JSON object from the response
            if (preg_match('/\{.*\}/s', $rawResponse, $matches)) {
                $data = json_decode($matches[0], true);
            } else {
                $data = null;
            }

            if (!$data || !isset($data['title'], $data['content'], $data['excerpt'])) {
                \Illuminate\Support\Facades\Log::warning('AI Response Parsing Failed', [
                    'raw_response' => $rawResponse,
                    'regex_matches' => $matches ?? null,
                    'json_error' => json_last_error_msg()
                ]);
                throw new \Exception('Invalid or missing JSON fields in AI response. See logs for raw response.');
            }

            $post = BlogPost::create([
                'title'     => $data['title'],
                'slug'      => Str::slug($data['title']) . '-' . Str::random(5),
                'content'   => $data['content'],
                'excerpt'   => $data['excerpt'],
                'category'  => $keywordObj->category ?? 'General',
                'is_draft'  => false,
                'published_at' => now(),
                'author_id' => null, // System post
            ]);

            $keywordObj->update(['last_used_at' => now()]);

            \App\Models\AdminLog::log(
                'trigger_autoblog',
                "Manually triggered AI blog generation for '{$keywordObj->keyword}'",
                null,
                ['post_id' => $post->id, 'keyword' => $keywordObj->keyword]
            );

            return response()->json([
                'message' => 'AI blog post generated and published successfully!',
                'post' => $post
            ]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AI Generation Trigger Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'AI Generation Failed: ' . $e->getMessage(),
                'debug' => $e->getTraceAsString()
            ], 500);
        }
    }
}
