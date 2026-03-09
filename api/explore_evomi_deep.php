<?php
$data = json_decode(file_get_contents('evomi_data.json'), true);
if (isset($data['data'])) {
    foreach ($data['data'] as $type => $info) {
        echo "## Product Type: $type ##\n";
        foreach ($info as $key => $val) {
           echo "  - $key: " . count($val) . " items\n";
        }
    }
}
