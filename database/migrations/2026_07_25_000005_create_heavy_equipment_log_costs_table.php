<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nominal biaya harian per item — diisi di lapangan (item dari katalog Finance).
        Schema::create('heavy_equipment_log_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('heavy_equipment_log_id')
                ->constrained('heavy_equipment_logs')
                ->cascadeOnDelete();
            $table->foreignUuid('heavy_equipment_cost_item_id')
                ->constrained('heavy_equipment_cost_items');
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();

            $table->index('heavy_equipment_log_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heavy_equipment_log_costs');
    }
};
