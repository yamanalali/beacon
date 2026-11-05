<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\TwoFactor\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TwoFactorService
    {
        return app(TwoFactorService::class);
    }

    public function test_enrollment_not_required_when_mfa_disabled(): void
    {
        config(['mfa.enabled' => false, 'mfa.enforce_after_days' => 0]);
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin, 'created_at' => now()->subYear()]);

        $this->assertFalse($this->service()->enrollmentEnforced());
        $this->assertFalse($this->service()->enrollmentRequired($admin));
        $this->assertNull($this->service()->enrollmentDeadline($admin));
    }

    public function test_grace_period_keeps_enrollment_optional_until_deadline(): void
    {
        config([
            'mfa.enabled' => true,
            'mfa.enforce_after_days' => 7,
            'mfa.enforced_from' => null,
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
            'created_at' => now()->subDays(3),
        ]);

        $deadline = $this->service()->enrollmentDeadline($admin);
        $this->assertNotNull($deadline);
        $this->assertTrue($deadline->isFuture());
        $this->assertFalse($this->service()->enrollmentRequired($admin));
    }

    public function test_enforced_from_overrides_created_at_for_deadline(): void
    {
        config([
            'mfa.enabled' => true,
            'mfa.enforce_after_days' => 2,
            'mfa.enforced_from' => now()->subDays(5)->toDateString(),
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
            'created_at' => now(),
        ]);

        $this->assertTrue($this->service()->enrollmentRequired($admin));
        $this->assertTrue($this->service()->enrollmentDeadline($admin)->isPast());
    }

    public function test_enrolled_admin_is_never_enrollment_required(): void
    {
        config(['mfa.enabled' => true, 'mfa.enforce_after_days' => 0]);
        $admin = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
            'created_at' => now()->subYear(),
        ]);
        $admin->forceFill([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->assertFalse($this->service()->enrollmentRequired($admin));
        $this->assertFalse($this->service()->mayDisable($admin));
    }

    public function test_sme_user_is_outside_enrollment_rules(): void
    {
        config(['mfa.enabled' => true, 'mfa.enforce_after_days' => 0]);
        $sme = User::factory()->create([
            'role' => UserRole::SmeUser,
            'created_at' => now()->subYear(),
        ]);

        $this->assertFalse($this->service()->appliesTo($sme));
        $this->assertFalse($this->service()->enrollmentRequired($sme));
    }

    public function test_deadline_is_exactly_created_at_plus_enforce_days(): void
    {
        config([
            'mfa.enabled' => true,
            'mfa.enforce_after_days' => 10,
            'mfa.enforced_from' => null,
        ]);
        $created = now()->subDays(4)->startOfSecond();
        $admin = User::factory()->create([
            'role' => UserRole::TechnicalAdmin,
            'created_at' => $created,
        ]);

        $deadline = $this->service()->enrollmentDeadline($admin);
        $this->assertNotNull($deadline);
        $this->assertTrue($deadline->equalTo($created->copy()->addDays(10)));
        $this->assertFalse($this->service()->enrollmentRequired($admin));
    }

    public function test_may_disable_only_when_enabled_confirmed_and_not_enforced(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $admin->forceFill([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ])->save();

        config(['mfa.enabled' => true, 'mfa.enforce_after_days' => null]);
        $this->assertTrue($this->service()->mayDisable($admin));

        config(['mfa.enforce_after_days' => 7]);
        $this->assertFalse($this->service()->mayDisable($admin));

        config(['mfa.enabled' => false, 'mfa.enforce_after_days' => null]);
        $this->assertFalse($this->service()->mayDisable($admin));
    }
}
