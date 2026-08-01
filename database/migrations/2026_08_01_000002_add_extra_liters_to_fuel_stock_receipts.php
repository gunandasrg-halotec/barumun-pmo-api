<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_stock_receipts', function (Blueprint $table) {
            $table->decimal('extra_liters', 10, 2)->default(0)->after('qty_40l');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_stock_receipts', function (Blueprint $table) {
            $table->dropColumn('extra_liters');
        });
    }
};
