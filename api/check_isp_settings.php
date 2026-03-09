<?php
$data = json_decode(file_get_contents('evomi_data.json'), true);
if (isset($data['data']['isp'])) {
    echo "ISP settings found. Keys:\n";
    print_r(array_keys($data['data']['isp']));
} else {
    echo "No 'isp' key in proxy_settings data.\n";
}
