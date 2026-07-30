<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_stock_receipt_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fuel_stock_receipt_id')
                  ->constrained('fuel_stock_receipts')
                  ->cascadeOnDelete();
            $table->string('storage_path', 500);
            $table->string('original_file_name', 255);
            $table->string('mime_type', 100)->default('image/jpeg');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_stock_receipt_photos');
    }
};
