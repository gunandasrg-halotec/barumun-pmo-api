<?php

namespace Database\Seeders;

use App\Models\HeavyEquipmentActivityType;
use Illuminate\Database\Seeder;

class HeavyEquipmentActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'ROLING',        'name' => 'Roling',                              'unit' => null,    'allow_date_range' => true,  'sort_order' => 1],
            ['code' => 'PARIT_BATAS',   'name' => 'Buat Parit Batas (3m x 3m x 2,5m)',  'unit' => 'm',     'allow_date_range' => false, 'sort_order' => 2],
            ['code' => 'PARIT_LEMBAH',  'name' => 'Buat Parit Lembah (1m x 1m x 1m)',   'unit' => 'm',     'allow_date_range' => false, 'sort_order' => 3],
            ['code' => 'CHIPPING',      'name' => 'Chipping',                            'unit' => 'pokok', 'allow_date_range' => false, 'sort_order' => 4],
            ['code' => 'TUMBANG_POKOK', 'name' => 'Tumbang Pokok Kayu',                  'unit' => 'pokok', 'allow_date_range' => false, 'sort_order' => 5],
            ['code' => 'BUKA_JALAN',    'name' => 'Buka Jalan / Terasan',                'unit' => 'm',     'allow_date_range' => false, 'sort_order' => 6],
        ];

        foreach ($types as $t) {
            HeavyEquipmentActivityType::updateOrCreate(['code' => $t['code']], $t + ['is_active' => true]);
        }
    }
}
