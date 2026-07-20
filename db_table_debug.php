<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$connections = ['orderfullfillment', 'manufacturing', 'inventory', env('DB_CONNECTION', 'pgsql')];
$searched = [
    "select table_schema, table_name from information_schema.tables where table_name ilike '%requisit%' order by table_schema, table_name",
    "select table_schema, table_name from information_schema.tables where table_name ilike '%order%' order by table_schema, table_name",
    "select table_schema, table_name from information_schema.tables where table_name ilike '%request%' order by table_schema, table_name",
];

foreach ($connections as $conn) {
    echo "=== connection: {$conn} ===" . PHP_EOL;
    try {
        foreach ($searched as $sql) {
            echo "SQL: {$sql}" . PHP_EOL;
            $rows = DB::connection($conn)->select($sql);
            foreach ($rows as $row) {
                echo "{$row->table_schema}.{$row->table_name}" . PHP_EOL;
            }
            echo PHP_EOL;
        }
    } catch (Exception $e) {
        echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;
}
