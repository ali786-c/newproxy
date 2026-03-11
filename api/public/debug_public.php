<?php
header('Content-Type: text/plain');
echo "--- PUBLIC Debugger ---\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "\n--- Laravel Check ---\n";
echo "Bootstrap exists? " . (file_exists(__DIR__ . '/../bootstrap/app.php') ? 'YES' : 'NO') . "\n";
echo "Vendor exists? " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'YES' : 'NO') . "\n";
