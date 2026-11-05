<?php

namespace Tests\Feature\Security;

use App\Enums\AdvisoryRecordStatus;
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

/** Additional logical edge cases for the security hardening surface. */
class SecurityLogicEdgeCasesTest extends TestCase
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

    private function enforceMfaImmediately(): void
    {
        config([
            'mfa.enabled' => true,
            'mfa.enforce_after_days' => 0,
            'mfa.enforced_from' => null,
        ]);
    }

    public function test_mfa_lock_still_allows_logout(): void
    {
        $this->enforceMfaImmediately();
        $admin = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
            'created_at' => now()->subDay(),
        ]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($admin);
        $this->get(route('dashboard.beacon'))->assertRedirect(route('two-factor.show'));

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('dashboard.beacon'))->assertRedirect(route('login'));
    }

    public function test_technical_admin_is_also_locked_when_enforcement_applies(): void
    {
        $this->enforceMfaImmediately();
        $tech = User::factory()->create([
            'role' => UserRole::TechnicalAdmin,
            'created_at' => now()->subDay(),
        ]);

        $this->post('/login', ['email' => $tech->email, 'password' => 'password'])
            ->assertRedirect(route('home'));
        $this->get(route('operations.security'))->assertRedirect(route('two-factor.show'));
        $this->get('/account/two-factor')->assertOk();
    }

    public function test_grace_period_expiry_locks_admin_mid_session(): void
    {
        config([
            'mfa.enabled' => true,
            'mfa.enforce_after_days' => 3,
            'mfa.enforced_from' => null,
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
            'created_at' => now()->subDays(2),
        ]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->get(route('dashboard.beacon'))->assertOk();

        $this->travel(2)->days();
        $this->assertTrue(app(TwoFactorService::class)->enrollmentRequired($admin->fresh()));
        $this->get(route('dashboard.beacon'))->assertRedirect(route('two-factor.show'));
        $this->get(route('users.index'))->assertRedirect(route('two-factor.show'));
    }

    public function test_pending_unconfirmed_secret_does_not_satisfy_enrollment(): void
    {
        $this->enforceMfaImmediately();
        $admin = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
            'created_at' => now()->subDay(),
        ]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/account/two-factor', ['current_password' => 'password']);
        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_confirmed_at);
        $this->assertTrue(app(TwoFactorService::class)->enrollmentRequired($admin));

        $this->get(route('dashboard.beacon'))->assertRedirect(route('two-factor.show'));
        $this->get('/account/two-factor')
            ->assertInertia(fn ($page) => $page
                ->where('mfa.pending', true)
                ->where('mfa.confirmed', false)
                ->where('mfa.enrollment_required', true));
    }

    public function test_wrong_password_cannot_start_mfa_enrollment_while_locked(): void
    {
        $this->enforceMfaImmediately();
        $admin = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
            'created_at' => now()->subDay(),
        ]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/account/two-factor', ['current_password' => 'wrong-password'])
            ->assertSessionHasErrors('current_password');
        $this->assertNull($admin->fresh()->two_factor_secret);
        $this->get(route('users.index'))->assertRedirect(route('two-factor.show'));
    }

    public function test_sme_of_other_enterprise_cannot_complete_foreign_follow_up(): void
    {
        $enterpriseA = Enterprise::query()->create(['business_name' => 'أ', 'location' => 'damascus']);
        $enterpriseB = Enterprise::query()->create(['business_name' => 'ب', 'location' => 'daraa']);
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $smeA = User::factory()->create(['role' => UserRole::SmeUser]);
        $smeB = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterpriseA->advisors()->attach($advisor->id);
        $enterpriseA->members()->attach($smeA->id);
        $enterpriseB->members()->attach($smeB->id);

        $this->actingAs($advisor)->post(route('enterprises.follow-ups.store', $enterpriseA), [
            'description' => 'متابعة أ',
            'responsible_user_id' => $smeA->id,
        ])->assertRedirect();

        $followUp = FollowUpAction::query()->sole();

        $this->actingAs($smeB)
            ->post(route('follow-ups.complete', $followUp))
            ->assertForbidden();

        $this->assertSame(AdvisoryRecordStatus::Open, $followUp->fresh()->status);
    }

    public function test_unrelated_member_cannot_complete_follow_up_they_are_not_responsible_for(): void
    {
        $enterprise = Enterprise::query()->create(['business_name' => 'ج', 'location' => 'damascus']);
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $responsible = User::factory()->create(['role' => UserRole::SmeUser]);
        $otherMember = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->advisors()->attach($advisor->id);
        $enterprise->members()->attach([$responsible->id, $otherMember->id]);

        $this->actingAs($advisor)->post(route('enterprises.follow-ups.store', $enterprise), [
            'description' => 'مهمة محددة',
            'responsible_user_id' => $responsible->id,
        ])->assertRedirect();

        $followUp = FollowUpAction::query()->sole();

        $this->actingAs($otherMember)
            ->post(route('follow-ups.complete', $followUp))
            ->assertForbidden();

        $this->assertSame(AdvisoryRecordStatus::Open, $followUp->fresh()->status);
    }

    public function test_inactive_responsible_user_cannot_complete_follow_up(): void
    {
        $enterprise = Enterprise::query()->create(['business_name' => 'د', 'location' => 'damascus']);
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->advisors()->attach($advisor->id);
        $enterprise->members()->attach($sme->id);

        $this->actingAs($advisor)->post(route('enterprises.follow-ups.store', $enterprise), [
            'description' => 'مهمة',
            'responsible_user_id' => $sme->id,
        ])->assertRedirect();

        $followUp = FollowUpAction::query()->sole();
        $sme->forceFill(['is_active' => false])->save();

        $this->actingAs($sme)
            ->post(route('follow-ups.complete', $followUp))
            ->assertForbidden();

        $this->assertSame(AdvisoryRecordStatus::Open, $followUp->fresh()->status);
    }

    public function test_referral_eloquent_fill_cannot_change_status(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $institution = $this->institution();

        $this->actingAs($advisor)->post(route('enterprises.referrals.store', $enterprise), [
            'mfi_institution_id' => $institution->id,
            'purpose_ar' => 'طلب',
            'disclosed_items' => ['enterprise_profile'],
        ])->assertRedirect();

        $referral = Referral::query()->sole();
        $referral->fill([
            'status' => Referral::STATUS_DECIDED,
            'purpose_ar' => 'طلب محدّث',
            'shared_at' => now(),
        ])->save();

        $referral->refresh();
        $this->assertSame(Referral::STATUS_DRAFT, $referral->status);
        $this->assertSame('طلب محدّث', $referral->purpose_ar);
        $this->assertNotNull($referral->shared_at);
    }

    public function test_user_invitation_fillable_rejects_unknown_columns(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $invite = new UserInvitation;
        $invite->fill([
            'name' => 'اختبار',
            'email' => 'fill@example.com',
            'role' => 'sme_user',
            'token_hash' => hash('sha256', 'abc'),
            'invited_by' => $admin->id,
            'expires_at' => now()->addDay(),
            'is_super_admin' => true,
            'password' => 'hacked',
        ]);

        $attrs = $invite->getAttributes();
        $this->assertSame('اختبار', $attrs['name']);
        $this->assertSame('fill@example.com', $attrs['email']);
        $this->assertSame('sme_user', $attrs['role']);
        $this->assertSame($admin->id, $attrs['invited_by']);
        $this->assertArrayHasKey('expires_at', $attrs);
        $this->assertArrayNotHasKey('is_super_admin', $attrs);
        $this->assertArrayNotHasKey('password', $attrs);
    }

    public function test_admin_create_user_ignores_role_escalation_outside_validated_fields(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'مستخدم',
            'email' => 'created@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::SmeUser->value,
            'is_active' => false,
            'mfi_institution_id' => 999,
            'two_factor_secret' => 'FORGEDSECRETFORGEDSECRETFORGEDSE',
        ])->assertRedirect(route('users.index'));

        $created = User::query()->where('email', 'created@example.com')->sole();
        $this->assertSame(UserRole::SmeUser, $created->role);
        $this->assertTrue($created->is_active);
        $this->assertNull($created->mfi_institution_id);
        $this->assertNull($created->two_factor_secret);
    }

    public function test_share_referral_still_requires_consent_after_status_injection_attempt(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $sme = $this->smeOf($enterprise);
        $institution = $this->institution();

        $this->actingAs($advisor)->post(route('enterprises.referrals.store', $enterprise), [
            'mfi_institution_id' => $institution->id,
            'purpose_ar' => 'تمويل',
            'disclosed_items' => ['enterprise_profile'],
            'status' => Referral::STATUS_SHARED,
        ])->assertRedirect();

        $referral = Referral::query()->sole();
        $this->assertSame(Referral::STATUS_DRAFT, $referral->status);

        $this->actingAs($advisor)->post(route('referrals.share', $referral))
            ->assertSessionHasErrors('consent');
        $this->assertSame(Referral::STATUS_DRAFT, $referral->fresh()->status);

        $this->actingAs($sme)->post(route('referrals.consent', $referral), ['confirm' => true])
            ->assertRedirect();
        $this->actingAs($advisor)->post(route('referrals.share', $referral))
            ->assertRedirect();
        $this->assertSame(Referral::STATUS_SHARED, $referral->fresh()->status);
    }

    public function test_enrolled_admin_challenge_rejects_stale_session_after_password_change(): void
    {
        config(['mfa.enabled' => true, 'mfa.enforce_after_days' => null]);
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));

        // Password change rotates security_stamp; pending MFA challenge must fail closed.
        $admin->forceFill(['password' => 'new-password-123'])->save();

        $this->post('/two-factor-challenge', ['code' => $this->otp($secret)])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_beacon_admin_can_complete_any_enterprise_follow_up(): void
    {
        $enterprise = Enterprise::query()->create(['business_name' => 'هـ', 'location' => 'damascus']);
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $enterprise->advisors()->attach($advisor->id);

        $this->actingAs($advisor)->post(route('enterprises.follow-ups.store', $enterprise), [
            'description' => 'متابعة إدارية',
        ])->assertRedirect();

        $followUp = FollowUpAction::query()->sole();

        $this->actingAs($admin)
            ->post(route('follow-ups.complete', $followUp))
            ->assertRedirect(route('enterprises.advisory.index', $enterprise));

        $this->assertSame(AdvisoryRecordStatus::Completed, $followUp->fresh()->status);
    }
}
