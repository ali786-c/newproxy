<?php
/**
 * LIVE SERVER IMAGE PATH FIXER
 * 
 * Instructions:
 * 1. Upload this file to your 'public/' directory (on cPanel) or 'api/' root.
 * 2. Access it via browser: https://upgraderproxy.com/api/fix_images.php (or wherever you uploaded it)
 * 3. Delete this file immediately after it finishes!
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BlogPost;

echo "<h1>AI Blog Image Path Synchronizer</h1>";
echo "<pre>";

$posts = BlogPost::all();
$fixedCount = 0;

foreach ($posts as $post) {
    $updated = false;
    
    // 1. Fix image_url if it misses the /api prefix (or starts with /storage)
    // Adjust logic if your live URL structure is different
    if ($post->image_url && str_starts_with($post->image_url, '/storage/')) {
        $post->image_url = '/api' . $post->image_url;
        $updated = true;
    }
    
    // 2. Fix content HTML
    if (str_contains($post->content, 'src="/storage/')) {
        $post->content = str_replace('src="/storage/', 'src="/api/storage/', $post->content);
        $updated = true;
    }
    
    // 3. Fix double asterisks in content (Convert **text** to <strong>text</strong>)
    if (str_contains($post->content, '**')) {
        $post->content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $post->content);
        $updated = true;
    }
    
    if ($updated) {
        $fixedCount++;
        echo "Fixed: {$post->title}\n";
        $post->save();
    }
}

echo "\n-------------------------\n";
echo "Total posts processed: " . $posts->count() . "\n";
echo "Total posts fixed: $fixedCount\n";
echo "Status: SUCCESS\n";
echo "<h1>IMPORTANT: DELETE THIS FILE NOW!</h1>";
echo "</pre>";
