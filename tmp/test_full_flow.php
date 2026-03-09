<?php
require __DIR__ . '/../api/vendor/autoload.php';
$app = require_once __DIR__ . '/../api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\GeminiService;
use App\Models\BlogPost;
use App\Services\BlogRenderer;

echo "--- STARTING FULL E2E TEST ---\n";

try {
    $gemini = app(GeminiService::class);
    $renderer = app(BlogRenderer::class);
    
    $keyword = "How Proxies Bypass Geo-Restrictions";
    $category = "Guides";
    
    echo "1. Generating Blog Content for '$keyword'...\n";
    $blogData = $gemini->generateBlogPost($keyword, $category, []);
    echo "   - Blog Title: {$blogData['title']}\n";
    
    echo "2. Generating Image Brief...\n";
    $brief = $gemini->generateImageBrief($blogData);
    if (!$brief) {
        throw new \Exception("Brief generation failed (returned null)!");
    }
    echo "   - Subject: {$brief['subject']}\n";
    
    echo "3. Generating Actual Image via Gemini 3 Pro...\n";
    $imageUrl = $gemini->generateFeaturedImage($brief);
    
    if (!$imageUrl) {
         echo "   - Image generation failed or hit quota limits.\n";
    } else {
         echo "   - Image saved to: $imageUrl\n";
    }
    
    echo "4. Rendering HTML content...\n";
    // We pass null for image in render since we store image_url separately in DB now
    $htmlContent = $renderer->render($blogData, null);
    $htmlPreview = substr($htmlContent, 0, 150) . "...";
    echo "   - HTML Generated: $htmlPreview\n";
    
    echo "\nTEST COMPLETED SUCCESSFULLY.\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
