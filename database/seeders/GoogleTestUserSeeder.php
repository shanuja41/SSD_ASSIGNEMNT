<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * GoogleTestUserSeeder
 * --------------------
 * Creates (or updates) one local user for demonstrating "Sign in with
 * Google" (OpenID Connect) against a real Google account. The project's
 * other seeded accounts use unroutable @*.test addresses, so they can't be
 * used to complete a real Google OAuth round trip.
 *
 * - Attaches the user to Greenfield Academy (falls back to the first
 *   available school if that one isn't seeded yet).
 * - Assigns the existing 'teacher' role — a normal, non-privileged role.
 *   Never assigns super-admin.
 * - Idempotent: safe to run more than once (updateOrCreate + syncRoles).
 * - The password set here is random and unused; this account is only ever
 *   meant to authenticate via Google OIDC.
 *
 * Run with:
 *   php artisan db:seed --class=GoogleTestUserSeeder
 */
class GoogleTestUserSeeder extends Seeder
{
    private const EMAIL = 'shanukanthan41@gmail.com';

    public function run(): void
    {
        $school = School::where('slug', 'greenfield-academy')->first()
            ?? School::withoutGlobalScopes()->orderBy('id')->first();

        if (! $school) {
            $this->command->error(
                'No school found to attach the Google test user to. Run DemoUserSeeder (or the main seeder) first.'
            );
            abort(1, 'No school available.');
        }

        // Role/permissions are owned by RolePermissionSeeder; firstOrCreate
        // here only guards against running this seeder in isolation.
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $user = User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name'      => 'Shanu Kanthan (Google Sign-In Demo)',
                'school_id' => $school->id,
                'password'  => Hash::make(Str::random(40)),
                'status'    => 'active',
            ]
        );

        $user->syncRoles(['teacher']);

        $this->command->info(
            "Google OIDC test user ready: {$user->email} (school_id={$user->school_id}, role=teacher)"
        );
    }
}
