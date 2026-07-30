<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_stock_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('receipt_date');
            $table->string('kebun', 100);
            $table->enum('fuel_type', ['solar', 'dex_lite']);
            $table->unsignedSmallInteger('qty_20l')->default(0);
            $table->unsignedSmallInteger('qty_30l')->default(0);
            $table->unsignedSmallInteger('qty_40l')->default(0);
            $table->decimal('total_liters', 10, 2)->default(0);
            $table->string('submitted_ip', 45)->nullable();
            $table->string('source', 20)->default('PUBLIC');
            $table->timestamps();

            $table->index(['kebun', 'receipt_date']);
            $table->index(['fuel_type', 'receipt_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_stock_receipts');
    }
};
