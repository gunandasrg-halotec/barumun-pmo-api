<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type', 100);
            $table->string('entity_id', 100);
            $table->string('action_type', 50); // CREATE | UPDATE | APPROVE | REJECT | DELETE | ARCHIVE | SUBMIT
            $table->foreignUuid('action_by')->constrained('users');
            $table->timestamp('action_at')->useCurrent();
            $table->json('old_value_json')->nullable();
            $table->json('new_value_json')->nullable();
            $table->text('remarks')->nullable();

            $table->index(['entity_type', 'entity_id']);
            $table->index('action_by');
            $table->index('action_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
