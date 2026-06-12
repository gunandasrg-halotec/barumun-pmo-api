<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wbd_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects');
            $table->integer('version_number');
            $table->string('status', 50)->default('DRAFT');
            $table->foreignUuid('based_on_version_id')->nullable()->constrained('wbd_versions');
            $table->foreignUuid('submitted_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'version_number']);
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'is_active']);
        });

        // Add active_wbd_version_id FK to projects after wbd_versions created
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignUuid('active_wbd_version_id')->nullable()->after('description')->constrained('wbd_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['active_wbd_version_id']);
            $table->dropColumn('active_wbd_version_id');
        });
        Schema::dropIfExists('wbd_versions');
    }
};
