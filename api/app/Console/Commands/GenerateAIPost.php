<?php

namespace App\Console\Commands;

use App\Models\AutoBlogKeyword;
use App\Models\BlogPost;
use App\Models\Setting;
use App\Services\GeminiService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class GenerateAIPost extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:generate-ai';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a blog post using Gemini AI based on active keywords';

    /**
     * Execute the console command.
     */
    public function handle(GeminiService $gemini, \App\Services\BlogRenderer $renderer, \App\Services\TelegramService $telegram, \App\Services\GoogleIndexingService $indexing, \App\Services\FacebookService $facebook)
    {
        Log::info('Cron: Starting Robust AI blog generation...');

        if (Setting::getValue('auto_blog_enabled', '0') !== '1') {
            Log::warning('Cron: Auto-blogging is currently disabled.');
            return;
        }

        $keywordObj = AutoBlogKeyword::active()
            ->orderBy('last_used_at', 'asc')
            ->first();

        if (!$keywordObj) {
            Log::error('Cron: No active keywords found.');
            return;
        }

        try {
            $recentTitles = BlogPost::latest()->take(10)->pluck('title')->toArray();

            // 1. Generate Structured Content
            $postContent = $gemini->generateBlogPost(
                $keywordObj->keyword, 
                $keywordObj->category ?? 'General', 
                $recentTitles
            );

            // 2. Generate specialized image brief for literal relevance
            $this->info("Generating image brief...");
            $imageBrief = $gemini->generateImageBrief($postContent);
            
            $this->info("Generating featured image...");
            $imageUrl = $imageBrief ? $gemini->generateFeaturedImage($imageBrief) : null;
            
            // 3. Render HTML
            $renderedHtml = $renderer->render($postContent, $imageUrl);

            // 4. Save
            $post = BlogPost::create([
                'title'     => $postContent['title'],
                'slug'      => Str::slug($postContent['title']) . '-' . Str::random(5),
                'content'   => $renderedHtml,
                'excerpt'   => $postContent['excerpt'],
                'category'  => $keywordObj->category ?? 'General',
                'image_url' => $imageUrl,
                'image_prompt' => null,
                'image_source' => $imageUrl ? 'ai_gemini_pro_cron' : 'none',
                'is_draft'  => false,
                'published_at' => now(),
                'author_id' => null,
            ]);

            $keywordObj->update(['last_used_at' => now()]);

            // 5. Telegram Share
            $telegram->sendBlogPost($post);

            // 6. Facebook Share
            $facebook->sendBlogPost($post);

            // 7. Google Indexing
            $indexing->publishUrl(url('/blog/' . $post->slug));

            Log::info("Cron: Success! Blog published and shared: {$post->title}");
            
        } catch (\Exception $e) {
            Log::error("Cron: AI Generation Failed: " . $e->getMessage());
            $this->error("AI Generation Failed: " . $e->getMessage());
        }
    }
}
