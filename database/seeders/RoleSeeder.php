<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['role_name' => RoleName::ADMINISTRATOR_SISTEM, 'description' => 'Mengelola master data, konfigurasi sistem, dan role'],
            ['role_name' => RoleName::PROJECT_MANAGER, 'description' => 'Mengelola WBD, progress, dokumen, dan report proyek'],
            ['role_name' => RoleName::DIREKSI, 'description' => 'Menyetujui atau menolak WBD dan revisi WBD'],
            ['role_name' => RoleName::FINANCE, 'description' => 'Mengelola dan menyetujui biaya aktual'],
            ['role_name' => RoleName::ADMIN_PROYEK, 'description' => 'Menginput progress, biaya, dan mengelola dokumen proyek'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insertOrIgnore([
                'id' => Str::uuid(),
                'role_name' => $role['role_name']->value,
                'description' => $role['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
