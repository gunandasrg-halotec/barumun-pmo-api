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
        Schema::table('heavy_equipment_activity_types', function (Blueprint $table) {
            $table->boolean('has_description')->default(false)->after('allow_date_range');
            $table->boolean('has_repair_cost')->default(false)->after('has_description');
        });

        $newTypes = [
            [
                'id'               => (string) Str::uuid(),
                'code'             => 'PERBAIKAN_MEKANIK',
                'name'             => 'Perbaikan Mekanik',
                'unit'             => null,
                'allow_date_range' => false,
                'has_description'  => true,
                'has_repair_cost'  => true,
                'sort_order'       => 90,
                'is_active'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'id'               => (string) Str::uuid(),
                'code'             => 'LOG_KERUSAKAN',
                'name'             => 'Log Kerusakan',
                'unit'             => null,
                'allow_date_range' => false,
                'has_description'  => true,
                'has_repair_cost'  => false,
                'sort_order'       => 91,
                'is_active'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'id'               => (string) Str::uuid(),
                'code'             => 'BTT_POKOK',
                'name'             => 'Bongkar, Tumbang, Tanam Pokok',
                'unit'             => 'pokok',
                'allow_date_range' => false,
                'has_description'  => false,
                'has_repair_cost'  => false,
                'sort_order'       => 92,
                'is_active'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ];

        foreach ($newTypes as $type) {
            // Skip jika kode sudah ada (idempoten)
            if (DB::table('heavy_equipment_activity_types')->where('code', $type['code'])->exists()) {
                continue;
            }
            DB::table('heavy_equipment_activity_types')->insert($type);
        }
    }

    public function down(): void
    {
        DB::table('heavy_equipment_activity_types')
            ->whereIn('code', ['PERBAIKAN_MEKANIK', 'LOG_KERUSAKAN', 'BTT_POKOK'])
            ->delete();

        Schema::table('heavy_equipment_activity_types', function (Blueprint $table) {
            $table->dropColumn(['has_description', 'has_repair_cost']);
        });
    }
};
