<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Demo data for local / staging share environments. Idempotent; never deletes data.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Requires APP_ENV=local|staging and DEMO_PASSWORD in .env.
 * Never runs in production.
 *
 * DemoMfiSeeder: demo MFI institution, linked demo MFI user, one consented shared referral.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'staging'])) {
            throw new RuntimeException('DemoSeeder runs only when APP_ENV is local or staging.');
        }

        if (blank(config('demo.password'))) {
            throw new RuntimeException('Set DEMO_PASSWORD in .env before running DemoSeeder.');
        }

        $this->call([
            DemoUsersSeeder::class,
            DemoEnterpriseSeeder::class,
            DemoFinanceSeeder::class,
            DemoMfiSeeder::class,
        ]);
    }
}
