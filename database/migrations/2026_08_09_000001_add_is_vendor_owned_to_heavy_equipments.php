<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heavy_equipments', function (Blueprint $table) {
            $table->boolean('is_vendor_owned')->default(false)->after('is_active');
        });

        DB::statement("ALTER TABLE heavy_equipment_logs MODIFY operator VARCHAR(100) NULL");

        // Data fix: alat "EXC-02 Vendor" yang baru ditambahkan memang milik pihak ketiga.
        DB::table('heavy_equipments')->where('code', 'EXC-02 Vendor')->update(['is_vendor_owned' => true]);
    }

    public function down(): void
    {
        Schema::table('heavy_equipments', function (Blueprint $table) {
            $table->dropColumn('is_vendor_owned');
        });

        DB::statement("ALTER TABLE heavy_equipment_logs MODIFY operator VARCHAR(100) NOT NULL");
    }
};
