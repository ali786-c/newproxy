<?php
header('Content-Type: text/plain');
echo "--- API Debugger ---\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'none') . "\n";
echo "\n--- Folder Check ---\n";
echo "Current Dir: " . __DIR__ . "\n";
echo "Public Dir: " . __DIR__ . '/public' . "\n";
echo "Is Public Dir readable? " . (is_readable(__DIR__ . '/public') ? 'YES' : 'NO') . "\n";
echo "Bridge .htaccess exists? " . (file_exists(__DIR__ . '/.htaccess') ? 'YES' : 'NO') . "\n";
