<?php
$data = json_decode(file_get_contents('evomi_data.json'), true);
if (isset($data['data']['residential']['isp'])) {
    print_r(array_slice($data['data']['residential']['isp'], 0, 5));
}
