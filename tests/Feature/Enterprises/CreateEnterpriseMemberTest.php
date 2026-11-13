<?php

namespace Tests\Feature\Enterprises;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateEnterpriseMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_attach_sme_user_from_enterprise_show(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $enterprise = Enterprise::query()->create([
            'business_name' => 'محمصة الرحمة',
            'location' => 'rural_damascus',
            'owner_name' => 'حسان ياسين',
        ]);

        $this->actingAs($admin)->post(route('enterprises.members.create', $enterprise), [
            'name' => 'حسان ياسين',
            'email' => 'sme-rahma@beacon.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $user = User::query()->where('email', 'sme-rahma@beacon.test')->first();
        $this->assertNotNull($user);
        $this->assertSame(UserRole::SmeUser, $user->role);
        $this->assertTrue($enterprise->members()->whereKey($user->id)->exists());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'enterprise.member_attached',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_non_admin_cannot_create_enterprise_member(): void
    {
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $enterprise = Enterprise::query()->create([
            'business_name' => 'منشأة',
            'location' => 'damascus',
        ]);
        $enterprise->advisors()->attach($advisor->id);

        $this->actingAs($advisor)->post(route('enterprises.members.create', $enterprise), [
            'name' => 'مستخدم',
            'email' => 'nope@beacon.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();
    }
}
