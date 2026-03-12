<?php
/**
 * Inspect BlogPost image_url
 */
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BlogPost;

$post = BlogPost::latest()->first();

if ($post) {
    echo "Post Title: " . $post->title . "\n";
    echo "Image URL: " . $post->image_url . "\n";
    echo "Public Path: " . public_path($post->image_url) . "\n";
    echo "File Exists (public_path): " . (file_exists(public_path($post->image_url)) ? "Yes" : "No") . "\n";
    
    $websiteUrl = config('app.url');
    echo "App URL: $websiteUrl\n";
    
    $relativeUrl = str_replace($websiteUrl, '', $post->image_url);
    echo "Relative URL (simple replace): $relativeUrl\n";
    echo "Path with simple relative: " . public_path($relativeUrl) . "\n";
    echo "File Exists (relative): " . (file_exists(public_path($relativeUrl)) ? "Yes" : "No") . "\n";

} else {
    echo "No blog post found.\n";
}
