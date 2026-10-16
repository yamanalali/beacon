<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Demo data for LOCAL use only. Idempotent; never deletes data.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Requires APP_ENV=local and DEMO_PASSWORD in .env.
 * Modular: add new demo areas as separate seeders in the list below
 * (e.g. readiness/referral, AI demo data).
 *
 * DemoMfiSeeder: demo MFI institution, linked demo MFI user, one consented shared referral.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoSeeder runs only when APP_ENV=local.');
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
