<?php

namespace Database\Seeders;

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
                'role_name' => 'Administrator Sistem',
                'password' => 'password123',
            ],
            [
                'full_name' => 'Project Manager',
                'email' => 'pm@company.com',
                'role_name' => 'Project Manager',
                'password' => 'password123',
            ],
            [
                'full_name' => 'Direktur Utama',
                'email' => 'direksi@company.com',
                'role_name' => 'Direksi',
                'password' => 'password123',
            ],
            [
                'full_name' => 'Finance Manager',
                'email' => 'finance@company.com',
                'role_name' => 'Finance',
                'password' => 'password123',
            ],
            [
                'full_name' => 'Admin Proyek',
                'email' => 'adminproyek@company.com',
                'role_name' => 'Admin Proyek',
                'password' => 'password123',
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->insertOrIgnore([
                'id' => Str::uuid(),
                'role_id' => $roles[$user['role_name']],
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
