<?php
header('Content-Type: text/plain');
echo "--- Routing Debugger ---\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'none') . "\n";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "\n--- File Checks ---\n";
$path = ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
echo "Extracted Path: " . $path . "\n";
echo "Is /api a directory? " . (is_dir(__DIR__ . '/api') ? 'YES' : 'NO') . "\n";
echo "Does api/public/index.php exist? " . (file_exists(__DIR__ . '/api/public/index.php') ? 'YES' : 'NO') . "\n";
echo "\n--- .htaccess Check ---\n";
if (file_exists(__DIR__ . '/.htaccess')) {
    echo "Root .htaccess found. Content (first 50 chars): " . substr(file_get_contents(__DIR__ . '/.htaccess'), 0, 50) . "...\n";
} else {
    echo "Root .htaccess NOT FOUND!\n";
}
