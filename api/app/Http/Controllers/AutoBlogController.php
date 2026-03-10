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
                // Telegram settings
                'telegram_bot_token' => Setting::getValue('telegram_bot_token') ?: '',
                'telegram_channel_id' => Setting::getValue('telegram_channel_id') ?: '',
                'telegram_auto_post_enabled' => Setting::getValue('telegram_auto_post_enabled', '0') === '1',
                // Google Indexing settings
                'google_indexing_enabled' => Setting::getValue('google_indexing_enabled', '0') === '1',
                'google_indexing_configured' => !empty(Setting::getValue('google_indexing_json')),
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
            'telegram_bot_token' => 'nullable|string',
            'telegram_channel_id' => 'nullable|string',
            'telegram_auto_post_enabled' => 'nullable|boolean',
            'google_indexing_enabled' => 'nullable|boolean',
            'google_indexing_json' => 'nullable|string',
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

        if ($request->has('telegram_bot_token')) {
            \App\Models\Setting::updateOrCreate(['key' => 'telegram_bot_token'], ['value' => $request->telegram_bot_token]);
        }

        if ($request->has('telegram_channel_id')) {
            \App\Models\Setting::updateOrCreate(['key' => 'telegram_channel_id'], ['value' => $request->telegram_channel_id]);
        }

        if ($request->has('telegram_auto_post_enabled')) {
            \App\Models\Setting::updateOrCreate(['key' => 'telegram_auto_post_enabled'], ['value' => $request->telegram_auto_post_enabled ? '1' : '0']);
        }

        if ($request->has('google_indexing_enabled')) {
            \App\Models\Setting::updateOrCreate(['key' => 'google_indexing_enabled'], ['value' => $request->google_indexing_enabled ? '1' : '0']);
        }

        if ($request->has('google_indexing_json')) {
            if (!empty($request->google_indexing_json)) {
                $encrypted = $this->encryptServiceKey($request->google_indexing_json);
                \App\Models\Setting::updateOrCreate(['key' => 'google_indexing_json'], ['value' => $encrypted]);
            }
            // If it's explicitly null/empty AND the field was sent, we might want to clear it, 
            // but for now, the UI preserves it.
        }

        \App\Models\AdminLog::log(
            'update_autoblog_settings',
            "Updated Auto-Blog Settings (including Telegram & Indexing)",
            null,
            $request->except(['gemini_api_key', 'telegram_bot_token', 'google_indexing_json']) // Don't log sensitive keys
        );

        return response()->json(['message' => 'Settings updated.']);
    }

    /**
     * Admin: Test Google Indexing API.
     */
    public function testIndexing(Request $request, \App\Services\GoogleIndexingService $indexing)
    {
        $testUrl = url('/'); // Use home page as test
        $success = $indexing->publishUrl($testUrl);

        if ($success) {
            return response()->json(['ok' => true, 'message' => 'Google Indexing connectivity verified!']);
        }

        return response()->json([
            'ok' => false, 
            'message' => 'Indexing test failed. Check laravel.log for details. Ensure Service Account has Owner access in Search Console.'
        ], 500);
    }

    /**
     * Admin: Manually index a specific blog post.
     */
    public function indexPost(Request $request, \App\Services\GoogleIndexingService $indexing)
    {
        \Illuminate\Support\Facades\Log::info('Admin Indexing Request Received', ['post_id' => $request->post_id]);

        $request->validate([
            'post_id' => 'required|exists:blog_posts,id'
        ]);

        $post = \App\Models\BlogPost::findOrFail($request->post_id);
        $url = url('/blog/' . $post->slug);

        $success = $indexing->publishUrl($url);

        if ($success) {
            return response()->json(['message' => 'URL submitted to Google successfully!']);
        }

        return response()->json(['message' => 'Indexing failed. Check logs.'], 500);
    }

    /**
     * Admin: Manually share a specific blog post to Telegram.
     */
    public function sharePostToTelegram(Request $request, \App\Services\TelegramService $telegram)
    {
        \Illuminate\Support\Facades\Log::info('Admin Telegram Share Request Received', ['post_id' => $request->post_id]);

        $request->validate([
            'post_id' => 'required|exists:blog_posts,id'
        ]);

        $post = \App\Models\BlogPost::findOrFail($request->post_id);
        
        $success = $telegram->sendBlogPost($post);

        if ($success) {
            return response()->json(['message' => 'Post shared to Telegram successfully!']);
        }

        return response()->json(['message' => 'Telegram sharing failed. Check logs.'], 500);
    }

    /**
     * Admin: Manually trigger a blog post generation.
     */
    public function trigger(Request $request, GeminiService $gemini, \App\Services\BlogRenderer $renderer, \App\Services\TelegramService $telegram, \App\Services\GoogleIndexingService $indexing)
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
            $postContent = null;
            $maxRetries = 2;
            $attempt = 0;

            while ($attempt < $maxRetries) {
                $attempt++;
                try {
                    $postContent = $gemini->generateBlogPost(
                        $keywordObj->keyword, 
                        $keywordObj->category ?? 'General', 
                        $recentTitles
                    );

                    // Validate word count (heuristic: total chars / 6)
                    $totalContent = '';
                    foreach ($postContent['sections'] as $s) $totalContent .= $s['content'];
                    $wordCount = str_word_count(strip_tags($totalContent));

                    if ($wordCount >= 400 && $wordCount <= 900) break; 
                    
                    \Illuminate\Support\Facades\Log::info("Word count out of range ({$wordCount}), retrying...", ['attempt' => $attempt]);
                } catch (\Exception $e) {
                    if ($attempt >= $maxRetries) throw $e;
                    \Illuminate\Support\Facades\Log::warning("AI Generation attempt {$attempt} failed, retrying...", ['error' => $e->getMessage()]);
                }
            }

            // 3. Generate specialized image brief for literal relevance
            $imageBrief = $gemini->generateImageBrief($postContent);
            $imageUrl = $imageBrief ? $gemini->generateFeaturedImage($imageBrief) : null;
            
            \Illuminate\Support\Facades\Log::info('AI Blog Manual Generation Result', [
                'title' => $postContent['title'],
                'has_image' => !empty($imageUrl),
                'image_url' => $imageUrl
            ]);
            
            // 4. Render Premium HTML
            $contentHtml = $renderer->render($postContent, $imageUrl);

            // 5. Create the Post
            $post = BlogPost::create([
                'title'     => $postContent['title'],
                'slug'      => Str::slug($postContent['title']) . '-' . Str::random(5),
                'content'   => $contentHtml,
                'excerpt'   => $postContent['excerpt'],
                'category'  => $keywordObj->category ?? 'General',
                'image_url' => $imageUrl,
                'image_prompt' => null, // We use $imageBrief structure app-side now
                'image_source' => $imageUrl ? 'ai_gemini_pro' : 'none',
                'is_draft'  => false,
                'published_at' => now(),
                'author_id' => null,
            ]);

            $keywordObj->update(['last_used_at' => now()]);

            // 6. Share to Telegram (Background aware service)
            $telegramShared = $telegram->sendBlogPost($post);

            // 7. Notify Google Indexing API
            $indexing->publishUrl(url('/blog/' . $post->slug));

            \App\Models\AdminLog::log(
                'trigger_autoblog',
                "Generated AI blog" . ($telegramShared ? " & Shared to Telegram" : "") . ": '{$post->title}'",
                null,
                ['post_id' => $post->id, 'keyword' => $keywordObj->keyword, 'telegram_shared' => $telegramShared]
            );

            return response()->json([
                'message' => $telegramShared 
                    ? 'AI blog post generated and shared to Telegram!' 
                    : 'AI blog post generated (Telegram sharing skipped or configured OFF).',
                'post' => $post,
                'telegram_shared' => $telegramShared
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

    /**
     * Encrypt Google Service Account JSON.
     */
    private function encryptServiceKey($data)
    {
        $key = config('services.google.indexing_key');
        if (!$key) return $data;

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', hex2bin($key), 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt Google Service Account JSON.
     */
    public function decryptServiceKey($data)
    {
        $key = config('services.google.indexing_key');
        if (!$key || empty($data)) return null;

        $decoded = base64_decode($data);
        $ivLen = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($decoded, 0, $ivLen);
        $ciphertext = substr($decoded, $ivLen);

        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', hex2bin($key), 0, $iv);
        
        if ($decrypted === false) {
            \Illuminate\Support\Facades\Log::error('Indexing JSON Decryption Failed: Invalid key or corrupted data.');
        }

        return $decrypted;
    }
}
