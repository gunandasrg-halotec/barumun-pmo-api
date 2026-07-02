<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = DB::table('roles')->pluck('id', 'role_name');

        $users = [
            [
                'full_name' => 'Administrator Sistem',
                'email' => 'admin@company.com',
                'role_name' => RoleName::ADMINISTRATOR_SISTEM,
                'password' => 'password123',
            ],
            [
                'full_name' => 'Manajer Kebun',
                'email' => 'pm@company.com',
                'role_name' => RoleName::PROJECT_MANAGER,
                'password' => 'password123',
            ],
            [
                'full_name' => 'Direktur Utama',
                'email' => 'direksi@company.com',
                'role_name' => RoleName::DIREKSI,
                'password' => 'password123',
            ],
            [
                'full_name' => 'Finance Manager',
                'email' => 'finance@company.com',
                'role_name' => RoleName::FINANCE,
                'password' => 'password123',
            ],
            [
                'full_name' => 'Admin Proyek',
                'email' => 'adminproyek@company.com',
                'role_name' => RoleName::ADMIN_PROYEK,
                'password' => 'password123',
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->insertOrIgnore([
                'id' => Str::uuid(),
                'role_id' => $roles[$user['role_name']->value],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'password' => Hash::make($user['password']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
