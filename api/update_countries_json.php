<?php
$filePath = 'c:/xampp/htdocs/lovable-export-d31f97fa/src/lib/data/countries.json';
$data = json_decode(file_get_contents($filePath), true);

if ($data) {
    // Ensure dc exists
    if (isset($data['dc'])) {
        // Add sdc
        $data['sdc'] = $data['dc'];
        
        // Add dc_ipv6 and dc_unmetered if missing (copy from dc)
        if (!isset($data['dc_ipv6'])) $data['dc_ipv6'] = $data['dc'];
        if (!isset($data['dc_unmetered'])) $data['dc_unmetered'] = $data['dc'];
        
        // Fix Korea name if needed
        if (isset($data['dc']['KR'])) $data['dc']['KR'] = "Korea";
        if (isset($data['sdc']['KR'])) $data['sdc']['KR'] = "Korea";
    }
    
    file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
    echo "Successfully updated countries.json\n";
} else {
    echo "Failed to decode countries.json\n";
}
