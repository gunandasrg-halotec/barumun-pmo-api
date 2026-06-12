<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['role_name' => 'Administrator Sistem', 'description' => 'Mengelola master data, konfigurasi sistem, dan role'],
            ['role_name' => 'Project Manager', 'description' => 'Mengelola WBD, progress, dokumen, dan report proyek'],
            ['role_name' => 'Direksi', 'description' => 'Menyetujui atau menolak WBD dan revisi WBD'],
            ['role_name' => 'Finance', 'description' => 'Mengelola dan menyetujui biaya aktual'],
            ['role_name' => 'Admin Proyek', 'description' => 'Menginput progress, biaya, dan mengelola dokumen proyek'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insertOrIgnore([
                'id' => Str::uuid(),
                'role_name' => $role['role_name'],
                'description' => $role['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
