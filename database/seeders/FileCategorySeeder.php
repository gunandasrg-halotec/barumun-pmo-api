<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FileCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Kontrak',
            'Berita Acara',
            'Invoice',
            'Foto Lapangan',
            'Approval',
            'Bukti Pembayaran',
            'Lainnya',
        ];

        foreach ($categories as $name) {
            DB::table('file_categories')->insertOrIgnore([
                'id' => Str::uuid(),
                'category_name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
