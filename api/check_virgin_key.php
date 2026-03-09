<?php
$data = json_decode(shell_exec('php check_isp_stock.php'), true);
if (isset($data['data']['residential'])) {
    echo "Categories under Residential:\n";
    print_r(array_keys($data['data']['residential']));
}
