<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HeavyEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        // Contoh alat berat
        DB::table('heavy_equipments')->insertOrIgnore([
            'id'         => Str::uuid(),
            'code'       => 'EXC-01',
            'type'       => 'Excavator',
            'brand'      => 'Komatsu PC200',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Katalog item biaya operasional (default mengacu sheet)
        $items = [
            ['Uang makan operator', 200000, 1],
            ['Premi operator',      160000, 2],
            ['Jaga malam',          160000, 3],
            ['Minyak / BBM',        820000, 4],
            ['Gaji bulanan',       1500000, 5],
            ['Upah harian operator',     0, 6],
        ];

        foreach ($items as [$name, $amount, $sort]) {
            // idempotent: nama item biaya tidak punya unique constraint,
            // jadi cek manual agar db:seed berulang tidak menduplikasi.
            $exists = DB::table('heavy_equipment_cost_items')->where('name', $name)->exists();
            if ($exists) {
                continue;
            }
            DB::table('heavy_equipment_cost_items')->insert([
                'id'             => Str::uuid(),
                'name'           => $name,
                'default_amount' => $amount,
                'is_active'      => true,
                'sort_order'     => $sort,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }
}
