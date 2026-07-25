<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heavy_equipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();   // Kode Alat Berat
            $table->string('type', 100);            // Jenis Alat Berat (mis. Excavator)
            $table->string('brand', 100);           // Merek Alat Berat (mis. Komatsu)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heavy_equipments');
    }
};
