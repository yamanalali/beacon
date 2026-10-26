<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use App\Support\AccountSecurity;
use App\Support\OfflinePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AccountSecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_change_revokes_other_database_sessions(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'stolen-session', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()]);
        $this->actingAs($user)->put('/account/password', ['current_password' => 'password', 'password' => 'New-password-123', 'password_confirmation' => 'New-password-123'])->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'stolen-session']);
        $this->get('/account')->assertOk();
    }

    public function test_password_reset_revokes_existing_sessions(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'stolen-session', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()]);
        $token = Password::createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'New-password-123', 'password_confirmation' => 'New-password-123'])->assertRedirect('/login');
        $this->assertDatabaseMissing('sessions', ['id' => 'stolen-session']);
    }

    public function test_pending_mfa_cannot_complete_after_password_changes(): void
    {
        config(['mfa.enabled' => true]);
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $user = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $user->forceFill(['password' => 'Reset-password-123'])->save();
        $this->post('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_mfa_enrollment_requires_current_password(): void
    {
        config(['mfa.enabled' => true]);
        $user = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $this->actingAs($user)->post('/account/two-factor')->assertSessionHasErrors('current_password');
        $this->assertNull($user->fresh()->two_factor_secret);
        $this->post('/account/two-factor', ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertNull($user->fresh()->two_factor_secret);
        $this->post('/account/two-factor', ['current_password' => 'password'])->assertRedirect('/account/two-factor');
    }

    public function test_reset_link_response_does_not_reveal_whether_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $known = $this->post('/forgot-password', ['email' => $user->email])->assertRedirect()->assertSessionMissing('errors')->getSession()->get('status');
        $unknown = $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertRedirect()->assertSessionMissing('errors')->getSession()->get('status');
        $this->assertSame($known, $unknown);
    }

    public function test_login_accepts_legacy_email_case_without_selecting_a_second_user(): void
    {
        $user = User::factory()->create(['email' => 'Mixed.Case@Example.test']);
        $this->post('/login', ['email' => 'mixed.case@example.test', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_permission_changes_reject_a_previously_authenticated_non_database_session(): void
    {
        config(['session.driver' => 'array']);
        $user = User::factory()->create();
        $stamp = AccountSecurity::stamp($user);
        // A direct database edit must also revoke the old session, without model events.
        DB::table('users')->where('id', $user->id)->update(['access_permissions' => json_encode(['export' => false])]);
        $this->withSession([AccountSecurity::SESSION_USER => $user->id, AccountSecurity::SESSION_STAMP => $stamp])
            ->actingAs($user->fresh())->get('/account')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_deactivation_and_reactivation_do_not_resurrect_old_credentials(): void
    {
        $user = User::factory()->create();
        $stamp = AccountSecurity::stamp($user);
        $token = $user->remember_token;
        $user->forceFill(['is_active' => false])->save();
        $user->forceFill(['is_active' => true])->save();
        $this->assertNotSame($stamp, AccountSecurity::stamp($user->fresh()));
        $this->assertNotSame($token, $user->fresh()->remember_token);
    }

    public function test_ambiguous_legacy_email_accounts_fail_closed(): void
    {
        User::factory()->create(['email' => 'Duplicate@Example.test']);
        User::factory()->create(['email' => 'duplicate@example.test']);
        $this->post('/login', ['email' => 'duplicate@example.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sensitive_validation_values_are_never_flashed_to_session(): void
    {
        $this->post('/reset-password', ['token' => 'sensitive-reset-token', 'email' => 'bad', 'password' => 'secret', 'password_confirmation' => 'other'])
            ->assertSessionHasErrors('email')->assertSessionMissing('_old_input.token')->assertSessionMissing('_old_input.password');
    }

    public function test_authentication_pages_prevent_ordinary_browser_caching(): void
    {
        $this->get('/login')->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->create())->get('/account')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_offline_authorization_changes_when_enterprise_membership_is_removed(): void
    {
        $user = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise = Enterprise::query()->create(['business_name' => 'Security test', 'location' => 'damascus']);
        $enterprise->members()->attach($user);
        $scope = OfflinePolicy::scope($user);
        $authorization = OfflinePolicy::authorization($user);
        $enterprise->members()->detach($user);
        $this->assertSame($scope, OfflinePolicy::scope($user));
        $this->assertNotSame($authorization, OfflinePolicy::authorization($user));
    }

    public function test_failed_mfa_attempts_keep_audit_and_account_limiter_state(): void
    {
        config(['cache.default' => 'database', 'cache.limiter' => 'database']);
        config(['mfa.enabled' => true]);
        $user = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $user->forceFill(['two_factor_secret' => app(Google2FA::class)->generateSecretKey(32), 'two_factor_confirmed_at' => now()])->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->post('/two-factor-challenge', ['code' => 'invalid-otp'])->assertSessionHasErrors('code')->assertSessionMissing('_old_input.code');
        $this->assertDatabaseHas('audit_logs', ['action' => 'mfa.challenge_failed', 'target_id' => (string) $user->id]);
        $this->assertSame(1, RateLimiter::attempts('mfa-challenge:'.$user->id));
    }
}
