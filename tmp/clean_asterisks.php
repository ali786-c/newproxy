<?php
require __DIR__ . '/../api/vendor/autoload.php';
$app = require_once __DIR__ . '/../api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BlogPost;

echo "Cleaning double asterisks from existing blogs...\n";

$posts = BlogPost::all();
$count = 0;

foreach ($posts as $post) {
    if (str_contains($post->content, '**')) {
        // Simple regex to replace **text** with <strong>text</strong>
        $post->content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $post->content);
        $post->save();
        $count++;
        echo "Cleaned: {$post->title}\n";
    }
}

echo "\nTotal cleaned: $count\n";
