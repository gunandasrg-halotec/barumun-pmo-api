<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Katalog item biaya operasional — di-setup oleh Finance di PMO web.
        Schema::create('heavy_equipment_cost_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);                       // mis. Uang makan operator
            $table->decimal('default_amount', 18, 2)->nullable(); // nominal default (prefill di lapangan)
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heavy_equipment_cost_items');
    }
};
