<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE deliveries ADD COLUMN IF NOT EXISTS deliver_to_warehouse VARCHAR(255)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE deliveries DROP COLUMN IF EXISTS deliver_to_warehouse');
    }
};
