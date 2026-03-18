<?php
$data = json_decode(file_get_contents('c:/xampp/htdocs/tmp/evomi_countries_full.json'), true);
if (!$data) {
    // Try reading again, maybe UTF-16 issues
    $content = file_get_contents('c:/xampp/htdocs/tmp/evomi_countries_full.json');
    $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
    $data = json_decode($content, true);
}

if ($data) {
    foreach ($data as $k => $v) {
        echo "$k: " . count($v) . "\n";
    }
} else {
    echo "Could not decode JSON\n";
}
