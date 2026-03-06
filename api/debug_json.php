<?php
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$logs = \App\Models\AdminLog::with(['admin', 'targetUser'])->latest()->paginate(2);
echo json_encode($logs, JSON_PRETTY_PRINT);
