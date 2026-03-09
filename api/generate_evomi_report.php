<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$evomiApiKey = config('services.evomi.key');

// 1. Get Balances/Products list from my_info
$ch = curl_init("https://reseller.evomi.com/v2/reseller/my_info");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-KEY: ' . $evomiApiKey]);
$myInfoRaw = curl_exec($ch);
curl_close($ch);
$myInfo = json_decode($myInfoRaw, true);

// 2. Get Proxy Settings (Targeting)
$settingsRaw = file_exists('evomi_data.json') ? file_get_contents('evomi_data.json') : null;
if (!$settingsRaw) {
    echo "Error: evomi_data.json missing. Run test_evomi.php first.\n";
    exit;
}
$settings = json_decode($settingsRaw, true);

$output = "# Evomi Proxy Infrastructure & Product Report\n";
$output .= "Generated on: " . date('Y-m-d H:i:s') . "\n\n";

$output .= "## 1. Available Products & Reseller Balances\n text\n";
$output .= "| Product Type | Status/Balance | Units |\n";
$output .= "| :--- | :--- | :--- |\n";

if (isset($myInfo['data']['products'])) {
    foreach ($myInfo['data']['products'] as $type => $info) {
        $balance = $info['balance'] ?? $info['credits'] ?? 0;
        $unit = $info['unit'] ?? 'Units';
        $output .= "| $type | $balance | $unit |\n";
    }
}
$output .= "\n---\n\n";

$output .= "## 2. Deep Infrastructure Details (Targeting)\n\n";

if (isset($settings['data'])) {
    foreach ($settings['data'] as $type => $data) {
        $output .= "### Product: " . ucfirst($type) . "\n";
        
        $countryCount = count($data['countries'] ?? []);
        $ispCount = count($data['isp'] ?? []);
        $cityCount = count($data['cities'] ?? []);
        $continentCount = count($data['continents'] ?? []);
        
        $output .= "- **Global Coverage:** $countryCount Countries\n";
        $output .= "- **ISP Network:** $ispCount Unique ISPs supported\n";
        $output .= "- **Targeting Tiers:** Continents ($continentCount), Regions, Cities ($cityCount)\n\n";
        
        $output .= "#### Top 20 Supported Countries (Alpha Order):\n";
        $countries = $data['countries'] ?? [];
        asort($countries);
        $topCountries = array_slice($countries, 0, 20);
        foreach ($topCountries as $code => $name) {
            $output .= "  - $name ($code)\n";
        }
        $output .= "  - *...and " . ($countryCount - 20) . " more.*\n\n";

        $output .= "#### Sample ISPs Supported:\n";
        $isps = $data['isp'] ?? [];
        $sampleIsps = array_slice($isps, 0, 15, true);
        foreach ($sampleIsps as $name => $info) {
             $output .= "  - $name (Country: " . ($info['countryCode'] ?? '??') . ")\n";
        }
        $output .= "  - *...and " . ($ispCount - 15) . " more.*\n\n";
        
        $output .= "---\n\n";
    }
}

$output .= "## 3. Technical Summary\n";
$output .= "- **API Base URL:** https://reseller.evomi.com/v2\n";
$output .= "- **Auth Method:** X-API-KEY Header\n";
$output .= "- **Protocols:** HTTP, HTTPS, SOCKS5 (standard across all types)\n";
$output .= "- **Authentication:** Username:Password (Subuser) and IP Whitelisting supported\n";

file_put_contents('evomi_full_details.md', $output);
echo "Full details report written to evomi_full_details.md\n";
