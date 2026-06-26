<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wbd_node_dependencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('predecessor_node_id')->constrained('wbd_nodes')->cascadeOnDelete();
            $table->foreignUuid('successor_node_id')->constrained('wbd_nodes')->cascadeOnDelete();
            $table->enum('dependency_type', ['FS', 'SS', 'FF', 'SF'])->default('FS');
            $table->timestamps();

            // A pair can only have one dependency type at a time
            $table->unique(['predecessor_node_id', 'successor_node_id'], 'unique_dep_pair');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wbd_node_dependencies');
    }
};
