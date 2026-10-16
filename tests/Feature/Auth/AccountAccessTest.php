<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_account(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => 'هذا الحساب موقوف',
        ]);

        $this->assertGuest();
    }

    public function test_login_regenerates_session(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
    }

    public function test_sme_user_cannot_open_users(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SmeUser,
        ]);

        $this->actingAs($user)->get('/users')->assertForbidden();
    }

    public function test_technical_admin_cannot_open_users(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::TechnicalAdmin,
        ]);

        $this->actingAs($user)->get('/users')->assertForbidden();
    }

    public function test_beacon_admin_can_create_a_user_with_a_stored_role(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
        ]);

        $this->actingAs($admin)->post('/users', [
            'name' => 'مستشار جديد',
            'email' => 'advisor@example.com',
            'password' => 'Secret-Pass-99',
            'password_confirmation' => 'Secret-Pass-99',
            'role' => UserRole::BeaconAdvisor->value,
        ])->assertRedirect(route('users.index'));

        $created = User::query()->where('email', 'advisor@example.com')->first();

        $this->assertNotNull($created);
        $this->assertSame(UserRole::BeaconAdvisor, $created->role);
        $this->assertTrue($created->is_active);
    }

    public function test_password_request_cannot_change_role(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SmeUser,
            'email' => 'sme@example.com',
        ]);

        $this->actingAs($user)->put(route('account.password'), [
            'current_password' => 'password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
            'role' => 'beacon_admin',
            'is_active' => false,
            'email' => 'hacked@example.com',
        ])->assertRedirect();

        $user->refresh();

        $this->assertSame(UserRole::SmeUser, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame('sme@example.com', $user->email);
    }

    public function test_deactivated_user_loses_access(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
        ]);
        $target = User::factory()->create([
            'role' => UserRole::SmeUser,
        ]);

        $this->actingAs($admin)
            ->post(route('users.deactivate', $target))
            ->assertRedirect();

        $target->refresh();
        $this->assertFalse($target->is_active);

        $this->actingAs($target)
            ->get('/account')
            ->assertForbidden();
    }

    public function test_audit_rows_exist_for_create_and_deactivate_without_password(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
        ]);

        $this->actingAs($admin)->post('/users', [
            'name' => 'حساب للمراجعة',
            'email' => 'audited@example.com',
            'password' => 'Secret-Pass-99',
            'password_confirmation' => 'Secret-Pass-99',
            'role' => UserRole::MfiUser->value,
        ]);

        $created = User::query()->where('email', 'audited@example.com')->firstOrFail();

        $this->actingAs($admin)->post(route('users.deactivate', $created));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.created',
            'target_id' => (string) $created->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.deactivated',
            'target_id' => (string) $created->id,
        ]);

        $encoded = AuditLog::query()->get()->toJson();
        $this->assertStringNotContainsString('Secret-Pass-99', $encoded);
    }

    public function test_there_is_no_register_route(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }
}
