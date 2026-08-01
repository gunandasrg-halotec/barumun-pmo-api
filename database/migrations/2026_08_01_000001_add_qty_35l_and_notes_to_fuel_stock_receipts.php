<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_stock_receipts', function (Blueprint $table) {
            $table->unsignedInteger('qty_35l')->default(0)->after('qty_30l');
            $table->text('notes')->nullable()->after('total_liters');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_stock_receipts', function (Blueprint $table) {
            $table->dropColumn(['qty_35l', 'notes']);
        });
    }
};
