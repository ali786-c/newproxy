<?php
$data = json_decode(file_get_contents('evomi_data.json'), true);
if (isset($data['data'])) {
    foreach ($data['data'] as $type => $info) {
        echo "## Type: $type ##\n";
        echo json_encode(array_keys($info), JSON_PRETTY_PRINT) . "\n";
    }
}
