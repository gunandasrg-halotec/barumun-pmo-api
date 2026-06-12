<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wbd_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wbd_version_id')->constrained('wbd_versions');
            $table->foreignUuid('parent_node_id')->nullable()->constrained('wbd_nodes');
            $table->string('node_type', 20)->default('ITEM'); // GROUP | ITEM
            $table->string('code', 100);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('volume', 18, 4)->nullable();
            $table->decimal('rate', 18, 2)->nullable();
            $table->decimal('planned_cost', 18, 2)->default(0);
            $table->decimal('component_percent', 10, 4)->nullable();
            $table->decimal('total_percent', 10, 4)->nullable();
            $table->date('start_date')->nullable();
            $table->integer('duration_days')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 50)->default('ACTIVE');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['wbd_version_id', 'code']);
            $table->index('parent_node_id');
            $table->index(['wbd_version_id', 'node_type']);
            $table->index(['wbd_version_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wbd_nodes');
    }
};
