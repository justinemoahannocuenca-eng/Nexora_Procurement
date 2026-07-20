<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$queries = [
    'manufacturing' => "select count(*) as cnt from requisitions",
    'orderfullfillment' => "select count(*) as cnt from order_reservations",
    'inventory' => "select count(*) as cnt from stock_levels",
];

foreach ($queries as $conn => $sql) {
    echo "=== {$conn} ===\n";
    try {
        $row = DB::connection($conn)->selectOne($sql);
        echo 'count=' . ($row->cnt ?? 'n/a') . "\n";
    } catch (Exception $e) {
        echo 'ERROR: ' . $e->getMessage() . "\n";
    }
    echo "\n";
}
