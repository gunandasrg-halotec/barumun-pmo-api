<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE fuel_stock_receipts MODIFY fuel_type ENUM('solar','dex_lite','pertadex') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE fuel_stock_receipts MODIFY fuel_type ENUM('solar','dex_lite') NOT NULL");
    }
};
