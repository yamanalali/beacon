<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\ReadinessAssessment;
use App\Models\User;
use Database\Seeders\DemoEnterpriseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private function asLocal(): void
    {
        $this->app['env'] = 'local';
        config(['demo.password' => 'test-demo-pass']);
    }

    public function test_seeder_is_idempotent_and_keeps_existing_admin(): void
    {
        Storage::fake('local');
        $this->asLocal();
        $existing = User::factory()->create(['email' => 'admin@beacon.test', 'role' => UserRole::BeaconAdmin]);
        $hash = $existing->password;

        $this->seed(DemoSeeder::class);
        $counts = [User::query()->count(), Enterprise::query()->count(), FinancialTransaction::query()->count()];
        $this->seed(DemoSeeder::class);

        $this->assertSame($counts, [User::query()->count(), Enterprise::query()->count(), FinancialTransaction::query()->count()]);
        $this->assertSame($hash, $existing->fresh()->password);

        foreach (config('demo.accounts') as $account) {
            $this->assertTrue(User::query()->where('email', $account['email'])->where('role', $account['role'])->where('is_active', true)->exists());
        }

        $sme = User::query()->where('email', 'sme@beacon.test')->sole();
        $this->assertTrue(Hash::check('test-demo-pass', $sme->password));

        $expectedNames = array_merge(
            [DemoEnterpriseSeeder::NAME],
            array_column(DemoEnterpriseSeeder::ADDITIONAL, 'business_name'),
        );
        $this->assertSame(count($expectedNames), Enterprise::query()->count());
        foreach ($expectedNames as $name) {
            $this->assertTrue(Enterprise::query()->where('business_name', $name)->exists(), $name);
        }

        $enterprise = DemoEnterpriseSeeder::enterprise();
        $this->assertTrue($enterprise->members()->whereKey($sme->id)->exists());
        $advisor = User::query()->where('email', 'advisor@beacon.test')->sole();
        foreach (Enterprise::query()->cursor() as $row) {
            $this->assertTrue($row->advisors()->whereKey($advisor->id)->exists(), $row->business_name);
        }
        $this->assertGreaterThan(5, FinancialTransaction::query()->count());
        $this->assertTrue(
            ReadinessAssessment::query()
                ->where('enterprise_id', $enterprise->id)
                ->where('status', ReadinessAssessment::STATUS_FINALIZED)
                ->exists(),
        );

        $this->actingAs($sme)->get(route('enterprises.reports.show', $enterprise))->assertOk();
        $this->actingAs($sme)->get(route('enterprises.readiness.index', $enterprise))->assertOk();
        $this->actingAs($advisor)->get(route('enterprises.index'))->assertOk();
    }

    public function test_seeder_refuses_outside_local(): void
    {
        config(['demo.password' => 'x']);
        $this->expectException(RuntimeException::class);
        $this->seed(DemoSeeder::class);
    }

    public function test_seeder_requires_demo_password(): void
    {
        $this->app['env'] = 'local';
        config(['demo.password' => null]);
        $this->expectException(RuntimeException::class);
        $this->seed(DemoSeeder::class);
    }

    public function test_login_never_exposes_demo_accounts(): void
    {
        $this->asLocal();
        config(['demo.password' => 'test-demo-pass']);
        $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->where('demoAccounts', null));
    }
}
