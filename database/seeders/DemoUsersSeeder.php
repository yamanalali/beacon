<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** One active user per role (see config/demo.php). Existing passwords are kept. */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('demo.accounts') as $account) {
            $user = User::query()->firstOrCreate(['email' => $account['email']], [
                'name' => $account['name'],
                'password' => Hash::make((string) config('demo.password')),
            ]);

            $user->forceFill([
                'role' => UserRole::from($account['role']),
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }
    }

    public static function user(UserRole $role): User
    {
        $email = collect(config('demo.accounts'))->firstWhere('role', $role->value)['email'];

        return User::query()->where('email', $email)->firstOrFail();
    }
}
