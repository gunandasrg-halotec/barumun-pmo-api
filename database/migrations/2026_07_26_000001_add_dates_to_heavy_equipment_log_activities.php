<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pekerjaan tertentu (mis. Roling) bisa mulai & selesai di tanggal berbeda.
        // Kalau null → dianggap sama dengan log_date (pekerjaan dalam hari yang sama).
        Schema::table('heavy_equipment_log_activities', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('activity_type');
            $table->date('end_date')->nullable()->after('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('heavy_equipment_log_activities', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
