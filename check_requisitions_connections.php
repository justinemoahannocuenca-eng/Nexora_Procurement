<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

foreach (['orderfullfillment', 'manufacturing', 'inventory'] as $conn) {
    echo "=== {$conn} ===\n";
    try {
        $schema = DB::connection($conn)->getSchemaBuilder();
        $hasTable = $schema->hasTable('requisitions') ? 'yes' : 'no';
        echo "has requisitions table: {$hasTable}\n";
        if ($hasTable === 'yes') {
            $row = DB::connection($conn)->selectOne("select count(*) as cnt from requisitions");
            echo 'row count: ' . ($row->cnt ?? 'n/a') . "\n";
        }
    } catch (Exception $e) {
        echo 'ERROR: ' . $e->getMessage() . "\n";
    }
    echo "\n";
}
