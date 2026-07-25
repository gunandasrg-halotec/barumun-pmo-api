<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laporan harian alat berat — diinput dari lapangan (halaman publik).
        Schema::create('heavy_equipment_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('heavy_equipment_id')->constrained('heavy_equipments');
            $table->date('log_date');
            $table->string('kebun', 100);
            $table->string('area', 10)->nullable();       // TM | TBM
            $table->string('operator', 100);
            $table->string('kenek', 100)->nullable();
            $table->decimal('fuel_liters', 10, 2)->nullable(); // BBM liter/hari
            $table->time('work_morning_start')->nullable();
            $table->time('work_morning_end')->nullable();
            $table->time('work_afternoon_start')->nullable();
            $table->time('work_afternoon_end')->nullable();
            $table->text('note')->nullable();             // Keterangan
            $table->string('source', 20)->default('PUBLIC');
            $table->string('submitted_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['heavy_equipment_id', 'log_date']);
            $table->index(['kebun', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heavy_equipment_logs');
    }
};
