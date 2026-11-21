<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::BeaconAdmin]);
    }

    public function test_admin_can_update_name_and_email_with_audit(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create([
            'role' => UserRole::SmeUser,
            'name' => 'اسم قديم',
            'email' => 'old@example.com',
        ]);

        $this->actingAs($admin)
            ->put(route('users.profile.update', $target), ['name' => 'اسم جديد', 'email' => ' New@Example.com '])
            ->assertRedirect()
            ->assertSessionHas('success', ui('flash.user_profile_updated'));

        $target->refresh();
        $this->assertSame('اسم جديد', $target->name);
        $this->assertSame('new@example.com', $target->email);
        $this->assertSame(UserRole::SmeUser, $target->role);

        $log = AuditLog::query()->where('action', 'user.profile_updated')->firstOrFail();
        $this->assertSame($admin->id, (int) $log->actor_id);
        $this->assertSame('old@example.com', $log->context['before']['email']);
        $this->assertSame('new@example.com', $log->context['after']['email']);
    }

    public function test_name_and_email_are_required(): void
    {
        $target = User::factory()->create(['role' => UserRole::SmeUser]);

        $this->actingAs($this->admin())
            ->put(route('users.profile.update', $target), ['name' => '', 'email' => ''])
            ->assertSessionHasErrors(['name', 'email']);
    }

    public function test_email_must_be_valid(): void
    {
        $target = User::factory()->create(['role' => UserRole::SmeUser]);

        $this->actingAs($this->admin())
            ->put(route('users.profile.update', $target), ['name' => 'س', 'email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_email_must_be_unique_case_insensitively_but_own_email_is_allowed(): void
    {
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $target = User::factory()->create(['role' => UserRole::SmeUser, 'email' => 'mine@example.com']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('users.profile.update', $target), ['name' => 'س', 'email' => 'TAKEN@example.com'])
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)
            ->put(route('users.profile.update', $target), ['name' => 'اسم', 'email' => 'mine@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('taken@example.com', $other->fresh()->email);
        $this->assertSame('اسم', $target->fresh()->name);
    }

    public function test_payload_cannot_change_role_or_activation(): void
    {
        $target = User::factory()->create(['role' => UserRole::SmeUser, 'is_active' => true]);

        $this->actingAs($this->admin())
            ->put(route('users.profile.update', $target), [
                'name' => 'اسم',
                'email' => 'safe@example.com',
                'role' => UserRole::BeaconAdmin->value,
                'is_active' => false,
                'access_permissions' => ['export' => false],
            ])
            ->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame(UserRole::SmeUser, $target->role);
        $this->assertTrue($target->is_active);
        $this->assertNull($target->access_permissions);
    }

    public function test_non_admin_roles_cannot_update_profiles(): void
    {
        $target = User::factory()->create(['role' => UserRole::SmeUser, 'name' => 'ثابت', 'email' => 'fixed@example.com']);

        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)
                ->put(route('users.profile.update', $target), ['name' => 'اختراق', 'email' => 'hack@example.com'])
                ->assertForbidden();
        }

        $target->refresh();
        $this->assertSame('ثابت', $target->name);
        $this->assertSame('fixed@example.com', $target->email);
    }

    public function test_inactive_admin_and_self_edit_are_forbidden(): void
    {
        $target = User::factory()->create(['role' => UserRole::SmeUser]);
        $inactive = User::factory()->create(['role' => UserRole::BeaconAdmin, 'is_active' => false]);
        $this->actingAs($inactive)
            ->put(route('users.profile.update', $target), ['name' => 'س', 'email' => 'x@example.com'])
            ->assertStatus(403);

        $admin = $this->admin();
        $this->actingAs($admin)
            ->put(route('users.profile.update', $admin), ['name' => 'س', 'email' => 'self@example.com'])
            ->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $target = User::factory()->create();

        $this->put(route('users.profile.update', $target), ['name' => 'س', 'email' => 'g@example.com'])
            ->assertRedirect(route('login'));
    }
}
