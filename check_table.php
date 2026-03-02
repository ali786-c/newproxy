<?php
require __DIR__ . '/api/vendor/autoload.php';
$app = require_once __DIR__ . '/api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "Checking notifications table...\n";
if (Schema::hasTable('notifications')) {
    echo "TABLE EXISTS!\n";
    $count = \DB::table('notifications')->count();
    echo "Record Count: $count\n";
} else {
    echo "TABLE MISSING!\n";
}
