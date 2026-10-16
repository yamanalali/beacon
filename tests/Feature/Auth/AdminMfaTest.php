<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\TwoFactor\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminMfaTest extends TestCase
{
    use RefreshDatabase;

    private function enableMfa(): void
    {
        config(['mfa.enabled' => true]);
    }

    /** @return array{0: User, 1: string, 2: list<string>} */
    private function enrolledAdmin(UserRole $role = UserRole::BeaconAdmin): array
    {
        $user = User::factory()->create(['role' => $role]);
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $codes = app(TwoFactorService::class)->regenerateRecoveryCodes($user);

        return [$user->fresh(), $secret, $codes];
    }

    private function otp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    public function test_disabled_by_default_and_login_is_unchanged_for_enrolled_admin(): void
    {
        $this->assertFalse(config('mfa.enabled'));
        [$admin] = $this->enrolledAdmin();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_routes_are_hidden_when_disabled(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);

        $this->actingAs($admin)->get('/account/two-factor')->assertNotFound();
        $this->post('/logout');
        $this->get('/two-factor-challenge')->assertNotFound();
    }

    public function test_admin_can_enroll_with_valid_code_and_gets_hashed_recovery_codes(): void
    {
        $this->enableMfa();
        $admin = User::factory()->create(['role' => UserRole::TechnicalAdmin]);

        $this->actingAs($admin)->post('/account/two-factor')->assertRedirect(route('two-factor.show'));
        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_confirmed_at);

        $this->actingAs($admin)->get('/account/two-factor')
            ->assertInertia(fn ($page) => $page->component('Account/TwoFactor')
                ->where('mfa.pending', true)
                ->where('mfa.setup_key', $admin->two_factor_secret));

        $this->actingAs($admin)->post('/account/two-factor/confirm', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $response = $this->actingAs($admin)
            ->post('/account/two-factor/confirm', ['code' => $this->otp($admin->two_factor_secret)])
            ->assertRedirect(route('two-factor.show'));

        $codes = $response->getSession()->get('mfa_new_recovery_codes');
        $this->assertCount(8, $codes);

        $admin->refresh();
        $this->assertTrue($admin->hasConfirmedTwoFactor());
        $raw = (string) $admin->getRawOriginal('two_factor_recovery_codes');
        foreach ($codes as $code) {
            $this->assertStringNotContainsString($code, $raw);
            $this->assertNotContains($code, $admin->two_factor_recovery_codes);
        }
        $this->assertStringNotContainsString($admin->two_factor_secret, (string) $admin->getRawOriginal('two_factor_secret'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'mfa.enrolled', 'target_id' => (string) $admin->id]);
    }

    public function test_enrolled_admin_must_pass_challenge(): void
    {
        $this->enableMfa();
        [$admin, $secret] = $this->enrolledAdmin();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get('/')->assertRedirect(route('login'));

        $this->get('/two-factor-challenge')->assertInertia(fn ($page) => $page->component('Auth/TwoFactorChallenge'));

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $this->otp($secret)])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_totp_code_cannot_be_replayed(): void
    {
        $this->enableMfa();
        [$admin, $secret] = $this->enrolledAdmin();
        $code = $this->otp($secret);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect(route('home'));
        $this->post('/logout');

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_challenge_without_password_step_redirects_to_login(): void
    {
        $this->enableMfa();
        [, $secret] = $this->enrolledAdmin();

        $this->get('/two-factor-challenge')->assertRedirect(route('login'));
        $this->post('/two-factor-challenge', ['code' => $this->otp($secret)])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_recovery_code_works_once_and_is_audited(): void
    {
        $this->enableMfa();
        [$admin, , $codes] = $this->enrolledAdmin();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => strtolower($codes[0])])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($admin);
        $this->assertCount(7, $admin->fresh()->two_factor_recovery_codes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mfa.recovery_code_used', 'target_id' => (string) $admin->id]);

        $this->post('/logout');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_disable_requires_password_and_is_audited(): void
    {
        $this->enableMfa();
        [$admin] = $this->enrolledAdmin();

        $this->actingAs($admin)->delete('/account/two-factor', ['current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        $this->assertTrue($admin->fresh()->hasConfirmedTwoFactor());

        $this->actingAs($admin)->delete('/account/two-factor', ['current_password' => 'password'])
            ->assertRedirect(route('two-factor.show'));
        $this->assertFalse($admin->fresh()->hasConfirmedTwoFactor());
        $this->assertDatabaseHas('audit_logs', ['action' => 'mfa.disabled', 'target_id' => (string) $admin->id]);
    }

    public function test_admin_without_enrollment_logs_in_normally_when_enabled(): void
    {
        // TODO: BUSINESS DECISION REQUIRED - enforcement/grace period; today enrollment is optional.
        $this->enableMfa();
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_non_admin_is_unaffected_and_cannot_open_settings(): void
    {
        $this->enableMfa();

        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser] as $role) {
            $user = User::factory()->create(['role' => $role]);
            // Even stray MFA data must not change a non-admin login.
            $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('home'));
            $this->assertAuthenticatedAs($user);
            $this->get('/account/two-factor')->assertForbidden();
            $this->post('/logout');
        }
    }

    public function test_secrets_are_hidden_from_serialization(): void
    {
        [$admin] = $this->enrolledAdmin();
        $json = $admin->toJson();

        $this->assertStringNotContainsString('two_factor_secret', $json);
        $this->assertStringNotContainsString('two_factor_recovery_codes', $json);
    }
}
