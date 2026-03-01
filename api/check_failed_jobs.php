<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$failed = DB::table('failed_jobs')->get();

if ($failed->count() > 0) {
    echo "Found " . $failed->count() . " failed jobs:\n";
    foreach ($failed as $job) {
        echo "ID: {$job->id} | Queue: {$job->queue} | Failed At: {$job->failed_at}\n";
        echo "Exception: " . substr($job->exception, 0, 200) . "...\n\n";
    }
} else {
    echo "No failed jobs found.\n";
}
