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
    public function handle(GeminiService $gemini, \App\Services\BlogRenderer $renderer)
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
            $blogData = $gemini->generateBlogPost(
                $keywordObj->keyword, 
                $keywordObj->category ?? 'General', 
                $recentTitles
            );

            // 2. Generate Image
            $imageUrl = null;
            if (!empty($blogData['image_prompt'])) {
                $imageUrl = $gemini->generateFeaturedImage($blogData['image_prompt']);
            }

            // 3. Render
            $contentHtml = $renderer->render($blogData);

            // 4. Save
            $post = BlogPost::create([
                'title'     => $blogData['title'],
                'slug'      => Str::slug($blogData['title']) . '-' . Str::random(5),
                'content'   => $contentHtml,
                'excerpt'   => $blogData['excerpt'],
                'category'  => $keywordObj->category ?? 'General',
                'image_url' => $imageUrl,
                'image_prompt' => $blogData['image_prompt'] ?? null,
                'image_source' => $imageUrl ? 'ai_gemini_cron' : 'none',
                'is_draft'  => false,
                'published_at' => now(),
                'author_id' => null,
            ]);

            $keywordObj->update(['last_used_at' => now()]);

            Log::info("Cron: Success! Blog published: {$post->title}");
            
        } catch (\Exception $e) {
            Log::error("Cron: AI Generation Failed: " . $e->getMessage());
        }
    }
}
