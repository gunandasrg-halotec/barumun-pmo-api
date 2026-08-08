<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wbd_versions', function (Blueprint $table) {
            // Menandai versi ini sebagai revisi in-place dari sebuah baseline (based_on_version_id
            // = baseline yang direvisi) — bukan versi baseline baru yang berdiri sendiri.
            $table->boolean('is_baseline_revision')->default(false)->after('based_on_version_id');
            // Diisi Direksi pada baseline AKTIF untuk membuka akses PM/Admin Proyek memulai revisi.
            // Dikonsumsi (dikosongkan lagi) begitu revisi mulai dibuat.
            $table->uuid('revision_unlocked_by')->nullable()->after('is_baseline_revision');
            $table->timestamp('revision_unlocked_at')->nullable()->after('revision_unlocked_by');

            $table->foreign('revision_unlocked_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wbd_versions', function (Blueprint $table) {
            $table->dropForeign(['revision_unlocked_by']);
            $table->dropColumn(['is_baseline_revision', 'revision_unlocked_by', 'revision_unlocked_at']);
        });
    }
};
