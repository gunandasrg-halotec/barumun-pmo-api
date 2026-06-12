<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects');
            // Polymorphic: WBD_NODE | PROGRESS_ENTRY
            $table->string('related_entity_type', 50)->nullable();
            $table->uuid('related_entity_id')->nullable();
            $table->foreignUuid('file_category_id')->constrained('file_categories');
            $table->string('file_type', 20); // DOCUMENT | IMAGE
            $table->string('original_file_name', 255);
            $table->string('storage_path', 500);
            $table->string('mime_type', 100);
            $table->string('caption', 500)->nullable();
            $table->date('photo_date')->nullable();
            $table->text('note')->nullable();
            $table->foreignUuid('uploaded_by')->constrained('users');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->string('file_status', 20)->default('ACTIVE'); // ACTIVE | ARCHIVED
            $table->timestamps();

            $table->index('project_id');
            $table->index('file_category_id');
            $table->index('file_type');
            $table->index(['related_entity_type', 'related_entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_files');
    }
};
