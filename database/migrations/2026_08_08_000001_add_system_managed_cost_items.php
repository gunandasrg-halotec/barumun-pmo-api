<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heavy_equipment_cost_items', function (Blueprint $table) {
            // Item biaya yang dikelola sistem (mis. gaji harian) — otomatis diisi saat
            // laporan disubmit, tidak ditampilkan sebagai field yang bisa diisi user
            // di form publik.
            $table->boolean('is_system_managed')->default(false)->after('sort_order');
        });

        $now = now();
        DB::table('heavy_equipment_cost_items')->insert([
            [
                'id'                => (string) Str::uuid(),
                'name'              => 'Gaji Operator',
                'default_amount'    => round(1500000 / 30, 2),
                'is_active'         => true,
                'sort_order'        => -2,
                'is_system_managed' => true,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'id'                => (string) Str::uuid(),
                'name'              => 'Gaji Helper',
                'default_amount'    => round(2000000 / 30, 2),
                'is_active'         => true,
                'sort_order'        => -1,
                'is_system_managed' => true,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('heavy_equipment_cost_items')->where('is_system_managed', true)->delete();
        Schema::table('heavy_equipment_cost_items', function (Blueprint $table) {
            $table->dropColumn('is_system_managed');
        });
    }
};
