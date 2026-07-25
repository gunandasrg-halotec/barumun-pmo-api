<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Foto dokumentasi laporan harian (disimpan di S3, prefix "pmo/").
        Schema::create('heavy_equipment_log_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('heavy_equipment_log_id')
                ->constrained('heavy_equipment_logs')
                ->cascadeOnDelete();
            $table->string('storage_path');
            $table->string('original_file_name');
            $table->string('mime_type', 100);
            $table->date('photo_date')->nullable();
            $table->timestamps();

            $table->index('heavy_equipment_log_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heavy_equipment_log_photos');
    }
};
