<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heavy_equipment_logs', function (Blueprint $table) {
            $table->decimal('fuel_liters_dex_lite', 10, 2)->nullable()->after('fuel_liters');
        });
    }

    public function down(): void
    {
        Schema::table('heavy_equipment_logs', function (Blueprint $table) {
            $table->dropColumn('fuel_liters_dex_lite');
        });
    }
};
