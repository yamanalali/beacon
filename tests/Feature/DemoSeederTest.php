<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_and_keeps_existing_admin(): void
    {
        Storage::fake('local');
        $existing = User::factory()->create(['email' => 'admin@beacon.test', 'role' => UserRole::BeaconAdmin]);
        $hash = $existing->password;

        $this->seed(DemoSeeder::class);
        $counts = [User::query()->count(), Enterprise::query()->count(), FinancialTransaction::query()->count()];
        $this->seed(DemoSeeder::class);

        $this->assertSame($counts, [User::query()->count(), Enterprise::query()->count(), FinancialTransaction::query()->count()]);
        $this->assertSame($hash, $existing->fresh()->password);

        foreach (UserRole::cases() as $role) {
            $this->assertTrue(User::query()->where('role', $role)->where('is_active', true)->where('email', 'like', '%@beacon.test')->exists(), $role->value);
        }

        $sme = User::query()->where('email', 'sme@beacon.test')->sole();
        $this->assertTrue(Hash::check(DemoSeeder::PASSWORD, $sme->password));

        $enterprise = Enterprise::query()->sole();
        $this->assertTrue($enterprise->members()->whereKey($sme->id)->exists());
        $this->assertTrue($enterprise->advisors()->where('email', 'advisor@beacon.test')->exists());
        $this->assertGreaterThan(5, FinancialTransaction::query()->count());

        $this->actingAs($sme)->get(route('enterprises.reports.show', $enterprise))->assertOk();
    }
}
