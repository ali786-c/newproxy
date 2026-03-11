<?php
/**
 * TEMPORARY DEBUG SCRIPT — DELETE AFTER USE
 * Upload to: upgraderproxy.com/debug_verify.php
 * Visit: https://upgraderproxy.com/api/debug_verify.php (NOT this, wrong path)
 * Actually place in htdocs root, visit: https://upgraderproxy.com/debug_verify.php
 */

// Secret key so random people can't run this
if (($_GET['key'] ?? '') !== 'debug2024') {
    die('Unauthorized. Use ?key=debug2024');
}

echo "<pre style='font-family:monospace;background:#111;color:#0f0;padding:20px;font-size:13px'>";
echo "=== VERIFICATION ROUTE DIAGNOSTIC ===\n\n";

define('API_PATH', __DIR__ . '/api');

// 1. Check if web.php has the verification route
$webPhp = API_PATH . '/routes/web.php';
echo "1. web.php exists: " . (file_exists($webPhp) ? 'YES' : 'NO') . "\n";
if (file_exists($webPhp)) {
    $content = file_get_contents($webPhp);
    echo "   Has verify-email-link route: " . (str_contains($content, 'verify-email-link') ? 'YES ✓' : 'NO ✗ <-- PROBLEM') . "\n";
    echo "\n   web.php contents:\n";
    echo htmlspecialchars($content) . "\n";
}

// 2. Check if route cache exists (can cause stale routes)
$routeCache = API_PATH . '/bootstrap/cache/routes-v7.php';
echo "\n2. Route cache exists: " . (file_exists($routeCache) ? 'YES (may need to be cleared!)' : 'NO (fresh routes)') . "\n";

// 3. Check bootstrap/app.php to confirm web routes are registered
$bootstrapApp = API_PATH . '/bootstrap/app.php';
if (file_exists($bootstrapApp)) {
    $content = file_get_contents($bootstrapApp);
    echo "\n3. bootstrap/app.php has web routes: " . (str_contains($content, 'web.php') ? 'YES ✓' : 'Checking...' . "\n");
    echo htmlspecialchars($content);
}

// 4. Try to clear route cache
echo "\n\n4. Clearing route cache...\n";
$cacheFiles = glob(API_PATH . '/bootstrap/cache/*.php');
$cleared = 0;
foreach ($cacheFiles as $cf) {
    if (basename($cf) !== 'packages.php' && basename($cf) !== 'services.php') {
        if (unlink($cf)) {
            echo "   Deleted: " . basename($cf) . "\n";
            $cleared++;
        }
    }
}
echo $cleared > 0 ? "   Cleared $cleared cache file(s)! ✓\n" : "   No cache files found.\n";

// 5. Check APP_URL
$envFile = API_PATH . '/.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES);
    echo "\n5. Environment Check:\n";
    foreach ($envLines as $line) {
        if (str_starts_with($line, 'APP_URL') || str_starts_with($line, 'FRONTEND_URL')) {
            echo "   $line\n";
        }
    }
}

echo "\n\n=== DONE ===\n";
echo "Next step: Visit https://upgraderproxy.com/api/auth/verify-email-link?test=1 to see if route works\n";
echo "</pre>";
