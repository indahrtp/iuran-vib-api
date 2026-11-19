<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@vib.test')],
            [
                'name'     => 'Administrator',
                'password' => env('ADMIN_PASSWORD', 'GantiSegera123'),
                'is_active' => true,
            ]
        );
        $admin->syncRoles('admin');

        // Akun contoh per role, hanya untuk lingkungan lokal
        if (app()->environment('local')) {
            foreach (['pengurus', 'bendahara', 'meter_reader', 'supervisor', 'penjaga_pos'] as $role) {
                $user = User::firstOrCreate(
                    ['email' => "{$role}@vib.test"],
                    ['name' => ucfirst(str_replace('_', ' ', $role)), 'password' => 'Password123', 'is_active' => true]
                );
                $user->syncRoles($role);
            }
        }
    }
}