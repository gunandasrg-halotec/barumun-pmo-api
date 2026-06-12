<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actual_cost_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects');
            $table->foreignUuid('progress_entry_id')->constrained('progress_entries');
            $table->decimal('amount', 18, 2);
            $table->date('transaction_date');
            $table->text('description')->nullable();
            $table->foreignUuid('entered_by')->constrained('users');
            // DRAFT | REVIEW | APPROVED | REJECTED
            $table->string('status', 50)->default('REVIEW');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('progress_entry_id');
            $table->index('transaction_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actual_cost_transactions');
    }
};
