<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wbd_revision_decisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wbd_version_id');
            $table->string('node_code', 50);
            $table->string('change_type', 20); // ADDED | MODIFIED | REMOVED
            $table->string('decision', 20); // APPROVED | REJECTED
            $table->text('reason')->nullable();
            $table->uuid('decided_by');
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->foreign('wbd_version_id')->references('id')->on('wbd_versions')->cascadeOnDelete();
            $table->foreign('decided_by')->references('id')->on('users');
            $table->index(['wbd_version_id', 'node_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wbd_revision_decisions');
    }
};
