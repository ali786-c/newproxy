<?php
require __DIR__ . '/../api/vendor/autoload.php';
$app = require_once __DIR__ . '/../api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = app(\App\Http\Controllers\AutoBlogController::class);
$request = new \Illuminate\Http\Request(['keyword_id' => 1]); // Adjust if keyword ID 1 doesn't exist

echo "Starting Manual AI Blog Generation Test with Contextual Image Prompts...\n";
$response = $controller->trigger($request, app(\App\Services\GeminiService::class), app(\App\Services\BlogRenderer::class));

echo "Response Status: " . $response->status() . "\n";
echo "Response Body: " . $response->getContent() . "\n";

$count = \App\Models\BlogPost::count();
echo "Total Blogs in DB: " . $count . "\n";

if ($count > 0) {
    $last = \App\Models\BlogPost::latest()->first();
    echo "Latest Title: " . $last->title . "\n";
    echo "Image URL: " . $last->image_url . "\n";
    echo "Image Prompt: " . $last->image_prompt . "\n";
}
