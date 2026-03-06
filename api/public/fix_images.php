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
    
    // Fix CTA Block Visibility (Upgrade to Premium Light Theme)
    // 1. Replace the solid blue version if it exists
    if (str_contains($post->content, 'bg-[#2563eb]" style="background-color: #2563eb;"')) {
        $post->content = str_replace(
            '<div class="bg-[#2563eb] rounded-2xl p-8 text-white text-center my-12 shadow-xl shadow-blue-900/10" style="background-color: #2563eb;">', 
            '<div class="rounded-3xl p-10 text-center my-12 border" style="background-color: #eef2ff; border-color: #e0e7ff;">', 
            $post->content
        );
        $post->content = str_replace('<h3 class="text-2xl font-bold mb-4 text-white">', '<h3 class="text-2xl font-bold mb-4" style="color: #1e1b4b;">', $post->content);
        $post->content = str_replace('<p class="text-blue-50 mb-6 max-w-xl mx-auto">', '<p class="mb-8 max-w-2xl mx-auto leading-relaxed" style="color: #3730a3;">', $post->content);
        $post->content = str_replace(
            '<a href="/dashboard" class="inline-block bg-white text-blue-700 font-bold px-8 py-3 rounded-full hover:bg-blue-50 transition-all transform hover:scale-105 active:scale-95">',
            '<a href="/dashboard" class="inline-block font-bold px-8 py-4 rounded-full shadow-lg transition-all" style="background-color: #4f46e5; color: white;">',
            $post->content
        );
    }
    
    // 2. Replace the gradient version if it exists
    if (str_contains($post->content, 'bg-gradient-to-r from-blue-600 to-indigo-800')) {
        $post->content = str_replace(
            '<div class="bg-gradient-to-r from-blue-600 to-indigo-800 rounded-2xl p-8 text-white text-center my-12 shadow-xl shadow-blue-900/10">', 
            '<div class="rounded-3xl p-10 text-center my-12 border" style="background-color: #eef2ff; border-color: #e0e7ff;">', 
            $post->content
        );
        $post->content = str_replace('<h3 class="text-2xl font-bold mb-4">', '<h3 class="text-2xl font-bold mb-4" style="color: #1e1b4b;">', $post->content);
        $post->content = str_replace('<p class="text-blue-100 mb-6 max-w-xl mx-auto">', '<p class="mb-8 max-w-2xl mx-auto leading-relaxed" style="color: #3730a3;">', $post->content);
        $post->content = str_replace(
            '<a href="/dashboard" class="inline-block bg-white text-blue-700 font-bold px-8 py-3 rounded-full hover:bg-blue-50 transition-all transform hover:scale-105 active:scale-95">',
            '<a href="/dashboard" class="inline-block font-bold px-8 py-4 rounded-full shadow-lg transition-all" style="background-color: #4f46e5; color: white;">',
            $post->content
        );
    }

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
