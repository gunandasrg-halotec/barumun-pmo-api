<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects');
            $table->foreignUuid('wbd_node_id')->constrained('wbd_nodes');
            $table->date('progress_date');
            $table->decimal('progress_volume', 18, 4);
            $table->text('note')->nullable();
            $table->foreignUuid('entered_by')->constrained('users');
            // DRAFT | PENDING_PM_APPROVAL | AUTO_APPROVED | APPROVED | REJECTED
            $table->string('status', 50)->default('PENDING_PM_APPROVAL');
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('wbd_node_id');
            $table->index('progress_date');
            $table->index('status');
            $table->index(['project_id', 'status', 'progress_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_entries');
    }
};
