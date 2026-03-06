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
    
    // Fix CTA Block Visibility (White-on-White to Blue)
    // We target the old gradient class and replace it with solid blue + direct style as backup
    if (str_contains($post->content, 'bg-gradient-to-r from-blue-600 to-indigo-800')) {
        $post->content = str_replace(
            'bg-gradient-to-r from-blue-600 to-indigo-800', 
            'bg-[#2563eb]" style="background-color: #2563eb;', 
            $post->content
        );
        // Ensure CTA heading is white
        $post->content = str_replace('text-2xl font-bold mb-4">', 'text-2xl font-bold mb-4 text-white">', $post->content);
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
