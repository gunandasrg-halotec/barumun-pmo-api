<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rincian pekerjaan per hari — bisa >1 jenis dalam satu laporan.
        Schema::create('heavy_equipment_log_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('heavy_equipment_log_id')
                ->constrained('heavy_equipment_logs')
                ->cascadeOnDelete();
            // ROLING | PARIT_BATAS | PARIT_LEMBAH | CHIPPING | TUMBANG_POKOK | BUKA_JALAN
            $table->string('activity_type', 50);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('volume', 12, 2)->nullable(); // hasil (meter / pokok)
            $table->string('unit', 20)->nullable();       // m | pokok
            $table->timestamps();

            $table->index('heavy_equipment_log_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heavy_equipment_log_activities');
    }
};
