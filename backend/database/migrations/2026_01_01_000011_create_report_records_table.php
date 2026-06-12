<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects');
            $table->string('report_type', 100); // Weekly | Monthly | Cost | Progress | etc
            $table->date('period_start');
            $table->date('period_end');
            $table->string('file_path', 500);
            $table->foreignUuid('generated_by')->constrained('users');
            $table->timestamp('generated_at')->useCurrent();
            $table->string('status', 20)->default('FINAL'); // FINAL | DELETED
            $table->timestamps();

            $table->index('project_id');
            $table->index(['project_id', 'report_type']);
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_records');
    }
};
