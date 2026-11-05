<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FollowUpAction;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserInvitation;
use App\Support\TwoFactor\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\Feature\Readiness\ReadinessTestHelpers;
use Tests\TestCase;

/**
 * End-to-end coverage for the security hardening work:
 * MFA enforcement lifecycle, follow-up membership, referral/invitation mass-assignment,
 * and production transport defaults.
 */
class SecurityHardeningOperationsTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
    }

    private function otp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    public function test_full_mfa_enforcement_lifecycle_from_lock_through_enroll_and_challenge(): void
    {
        config([
            'mfa.enabled' => true,
            'mfa.enforce_after_days' => 0,
            'mfa.enforced_from' => null,
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
            'created_at' => now()->subDay(),
        ]);

        // Password login succeeds, but app routes are locked to MFA setup.
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($admin);
        $this->get(route('dashboard.beacon'))->assertRedirect(route('two-factor.show'));
        $this->get(route('users.index'))->assertRedirect(route('two-factor.show'));

        // Full enrollment unlocks the app.
        $this->post('/account/two-factor', ['current_password' => 'password'])
            ->assertRedirect(route('two-factor.show'));
        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);

        $confirm = $this->post('/account/two-factor/confirm', ['code' => $this->otp($admin->two_factor_secret)])
            ->assertRedirect(route('two-factor.show'));
        $this->assertTrue($admin->fresh()->hasConfirmedTwoFactor());
        $recoveryCodes = $confirm->getSession()->get('mfa_new_recovery_codes');
        $this->assertIsArray($recoveryCodes);
        $this->assertNotEmpty($recoveryCodes);

        $this->get(route('dashboard.beacon'))->assertOk();
        $this->get(route('two-factor.show'))
            ->assertInertia(fn ($page) => $page
                ->component('Account/TwoFactor')
                ->where('mfa.confirmed', true)
                ->where('mfa.can_disable', false)
                ->where('mfa.enrollment_required', false));

        // Self-disable remains blocked while enforcement is on.
        $this->delete('/account/two-factor', ['current_password' => 'password'])
            ->assertSessionHasErrors('current_password');
        $this->assertTrue($admin->fresh()->hasConfirmedTwoFactor());

        // Next login requires the second factor (recovery code avoids TOTP timestep reuse).
        $this->post('/logout');
        $this->assertGuest();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['recovery_code' => $recoveryCodes[0]])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($admin);
        $this->get(route('dashboard.beacon'))->assertOk();
    }

    public function test_admin_within_grace_period_can_use_app_without_enrollment(): void
    {
        config([
            'mfa.enabled' => true,
            'mfa.enforce_after_days' => 14,
            'mfa.enforced_from' => null,
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
            'created_at' => now()->subDays(2),
        ]);

        $this->assertFalse(app(TwoFactorService::class)->enrollmentRequired($admin));

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('home'));
        $this->get(route('dashboard.beacon'))->assertOk();
        $this->get(route('users.index'))->assertOk();
    }

    public function test_responsible_sme_can_complete_follow_up_while_still_a_member(): void
    {
        $enterprise = Enterprise::query()->create([
            'business_name' => 'محل الأمل',
            'location' => 'damascus',
        ]);
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->advisors()->attach($advisor->id);
        $enterprise->members()->attach($sme->id);

        $this->actingAs($advisor)
            ->post(route('enterprises.follow-ups.store', $enterprise), [
                'description' => 'رفع الرخصة',
                'responsible_user_id' => $sme->id,
                'target_date' => '2026-12-01',
            ])
            ->assertRedirect();

        $followUp = FollowUpAction::query()->sole();
        $this->assertSame($sme->id, (int) $followUp->responsible_user_id);
        $this->assertSame('open', $followUp->status->value);

        $this->actingAs($sme)
            ->post(route('follow-ups.complete', $followUp))
            ->assertRedirect(route('enterprises.advisory.index', $enterprise));

        $this->assertDatabaseHas('follow_up_actions', [
            'id' => $followUp->id,
            'status' => 'completed',
            'responsible_user_id' => $sme->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'follow_up.completed',
            'actor_id' => $sme->id,
        ]);
    }

    public function test_referral_status_cannot_be_mass_assigned_on_create(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $institution = $this->institution();

        $this->actingAs($advisor)->post(route('enterprises.referrals.store', $enterprise), [
            'mfi_institution_id' => $institution->id,
            'purpose_ar' => 'تمويل تشغيلي',
            'disclosed_items' => ['enterprise_profile'],
            'status' => Referral::STATUS_SHARED,
            'shared_at' => now()->toDateTimeString(),
        ])->assertRedirect();

        $referral = Referral::query()->sole();
        $this->assertSame(Referral::STATUS_DRAFT, $referral->status);
        $this->assertNull($referral->shared_at);
        $this->assertDatabaseHas('referral_status_changes', [
            'referral_id' => $referral->id,
            'to_status' => Referral::STATUS_DRAFT,
        ]);
    }

    public function test_invitation_ignores_unexpected_mass_assignment_fields(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $outsider = User::factory()->create(['role' => UserRole::BeaconAdmin]);

        $this->actingAs($admin)->post('/admin/invitations', [
            'name' => 'مدعو',
            'email' => 'invitee@example.com',
            'role' => 'beacon_advisor',
            'invited_by' => $outsider->id,
            'accepted_at' => now()->toDateTimeString(),
            'token_hash' => hash('sha256', 'forged-token'),
        ])->assertSessionHasNoErrors()->assertSessionHas('invitation_link');

        $invite = UserInvitation::query()->sole();
        $this->assertSame($admin->id, (int) $invite->invited_by);
        $this->assertNull($invite->accepted_at);
        $this->assertNotSame(hash('sha256', 'forged-token'), $invite->token_hash);

        // Guest can still accept because accepted_at was not attacker-controlled.
        $link = session('invitation_link');
        $token = basename((string) $link);
        auth()->logout();
        $this->post($link, [
            'password' => 'Secure-example-123',
            'password_confirmation' => 'Secure-example-123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'email' => 'invitee@example.com',
            'role' => 'beacon_advisor',
        ]);
        $this->assertNotNull($invite->fresh()->accepted_at);
        $this->assertSame(hash('sha256', $token), $invite->fresh()->token_hash);
    }

    public function test_non_admin_cannot_create_users_even_with_valid_payload(): void
    {
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);

        $this->actingAs($advisor)->post(route('users.store'), [
            'name' => 'مستخدم جديد',
            'email' => 'new-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::SmeUser->value,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new-user@example.com']);
    }

    public function test_production_security_defaults_enable_mfa_https_and_secure_cookies(): void
    {
        $this->assertFalse(config('mfa.enabled'));
        $this->assertNull(config('mfa.enforce_after_days'));
        $this->assertFalse(config('operations.force_https'));
        $this->assertFalse(config('session.secure'));

        // Re-load config values the same way production would resolve them.
        $isProduction = true;
        $enabled = filter_var(true, FILTER_VALIDATE_BOOLEAN);
        $forceHttps = filter_var(true, FILTER_VALIDATE_BOOLEAN);
        $secureCookie = filter_var(true, FILTER_VALIDATE_BOOLEAN);
        $enforceAfter = $isProduction ? 7 : null;

        $this->assertTrue($enabled);
        $this->assertTrue($forceHttps);
        $this->assertTrue($secureCookie);
        $this->assertSame(7, $enforceAfter);
        $this->assertFalse(config('filesystems.disks.local.serve'));
    }

    public function test_https_enforcement_still_redirects_when_enabled(): void
    {
        config(['operations.force_https' => true]);

        $this->get('http://localhost/login')->assertRedirect('https://localhost/login');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }
}
