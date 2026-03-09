<?php
$data = json_decode(shell_exec('php check_isp_stock.php'), true);
if (isset($data['data'])) {
    echo "Summary of ISP Structure:\n";
    $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($data['data']), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $key => $val) {
        if ($key === 'price' || $key === 'stock') {
            $path = [];
            for($i=0; $i<$iterator->getDepth(); $i++) {
                $path[] = $iterator->getSubIterator($i)->key();
            }
            echo implode(' -> ', $path) . " -> $key: $val\n";
        }
    }
}
