<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $roles = [
            'super-admin',
            'school-admin',
            'principal',
            'teacher',
            'accountant',
            'librarian',
            'receptionist',
            'student',
            'parent',
            'driver',
            'warden',
            'store-manager',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // FIX (Vuln #5): password comes from .env, not hardcoded in source code.
        // If SEED_ADMIN_PASSWORD is not set, a random password is generated and shown once.
        $password = env('SEED_ADMIN_PASSWORD') ?: Str::random(20);
        if (! env('SEED_ADMIN_PASSWORD')) {
            $this->command?->warn("SEED_ADMIN_PASSWORD not set. Random password for admin@genius-sms.test: {$password}");
        }

        // Create super admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@genius-sms.test'],
            [
                'name'     => 'Super Admin',
                'password' => bcrypt($password),
                'status'   => 'active',
            ]
        );

        $admin->assignRole('super-admin');
    }
}