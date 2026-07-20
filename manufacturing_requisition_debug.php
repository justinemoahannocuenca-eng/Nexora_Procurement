<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::connection('manufacturing')->select("select column_name, data_type from information_schema.columns where table_name='requisitions' order by ordinal_position");
foreach ($rows as $row) {
    echo $row->column_name . ' (' . $row->data_type . ')' . PHP_EOL;
}
