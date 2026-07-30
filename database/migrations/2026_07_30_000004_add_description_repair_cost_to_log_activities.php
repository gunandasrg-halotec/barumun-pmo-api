<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heavy_equipment_log_activities', function (Blueprint $table) {
            $table->text('description')->nullable()->after('unit');
            $table->decimal('repair_cost', 18, 2)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('heavy_equipment_log_activities', function (Blueprint $table) {
            $table->dropColumn(['description', 'repair_cost']);
        });
    }
};
