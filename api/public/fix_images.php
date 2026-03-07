<?php
/**
 * LIVE SERVER IMAGE & STYLE FIXER (V2)
 * 
 * Instructions:
 * 1. Upload this file to your 'public/' directory (on cPanel).
 * 2. Access it via browser: https://upgraderproxy.com/api/fix_images.php
 * 3. Delete this file immediately after it finishes!
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BlogPost;

echo "<h1>AI Blog Style & Path Sync (V2)</h1>";
echo "<pre>";

$posts = BlogPost::all();
$fixedCount = 0;

foreach ($posts as $post) {
    $updated = false;
    
    // 1. Fix image_url field
    if ($post->image_url && str_starts_with($post->image_url, '/storage/')) {
        $post->image_url = '/api' . $post->image_url;
        $updated = true;
    }
    
    // 2. Deep Fix for HTML Content
    $oldContent = $post->content;
    
    // Fix Image Paths
    $post->content = str_replace('src="/storage/', 'src="/api/storage/', $post->content);
    
    // Fix Double Asterisks
    $post->content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $post->content);
    
    // Fix Dashboard Link to App Link
    $post->content = str_replace(['href="/dashboard"', "href='/dashboard'"], 'href="/app"', $post->content);
    
    // 3. Fix CTA Block Visibility (Upgrade to Premium Light Theme using Regex)
    // This regex looks for the container holding "Ready to level up your proxy game?" 
    // and captures the paragraph text to preserve it, ignoring whatever old classes it had.
    $pattern = '/<div[^>]*>[\s\n]*<h3[^>]*>Ready to level up your proxy game\?<\/h3>[\s\n]*<p[^>]*>(.*?)<\/p>.*?<\/div>/is';

    $replacement = <<<HTML
<div class="rounded-3xl p-10 text-center my-12 border" style="background-color: #eef2ff; border-color: #e0e7ff;">
    <h3 class="text-2xl font-bold mb-4" style="color: #1e1b4b;">Ready to level up your proxy game?</h3>
    <p class="mb-8 max-w-2xl mx-auto leading-relaxed" style="color: #3730a3;">$1</p>
    <a href="/app" class="inline-block font-bold px-8 py-4 rounded-full shadow-lg transition-all" style="background-color: #4f46e5; color: white;">
        Get Started Now
    </a>
</div>
HTML;

    $post->content = preg_replace($pattern, $replacement, $post->content);

    if ($post->content !== $oldContent || $updated) {
        $fixedCount++;
        echo "Fixed Style & Paths: {$post->title}\n";
        $post->save();
        $updated = true;
    }
}

echo "\n-------------------------\n";
echo "Total posts processed: " . $posts->count() . "\n";
echo "Total posts updated: $fixedCount\n";
echo "Status: SUCCESS\n";
echo "<h1>IMPORTANT: DELETE THIS FILE NOW!</h1>";
echo "</pre>";
