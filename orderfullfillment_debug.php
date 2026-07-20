<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::connection('orderfullfillment')->select("select table_name from information_schema.tables where table_schema='public' order by table_name");
foreach ($rows as $row) {
    echo $row->table_name . PHP_EOL;
}
