<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent: running twice won't create duplicates.
 * Password comes from ADMIN_SEED_PASSWORD env, or defaults to "password".
 * After first login, change immediately via admin settings.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email    = env('ADMIN_SEED_EMAIL', 'admin@codearena.test');
        $password = env('ADMIN_SEED_PASSWORD', 'password');

        User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => 'Administrator',
                'password' => Hash::make($password),
                'role'     => User::ROLE_ADMIN,
            ]
        );

        $this->command->info("Admin user: {$email}  password: {$password}");
    }
}
