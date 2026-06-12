<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('project_code', 100)->unique();
            $table->string('project_name', 200);
            $table->string('client_name', 200);
            $table->string('location', 200);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 50)->default('ACTIVE');
            $table->text('description')->nullable();
            // active_wbd_version_id added after wbd_versions table is created
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index('status');
            $table->index('client_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
