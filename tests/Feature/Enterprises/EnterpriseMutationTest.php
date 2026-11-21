<?php

namespace Tests\Feature\Enterprises;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseMutationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::BeaconAdmin]);
    }

    private function enterprise(string $name = 'منشأة اختبار'): Enterprise
    {
        return Enterprise::query()->create([
            'business_name' => $name,
            'location' => 'damascus',
        ]);
    }

    public function test_admin_can_archive_and_restore_enterprise(): void
    {
        $admin = $this->admin();
        $enterprise = $this->enterprise();

        $this->actingAs($admin)
            ->post(route('enterprises.archive', $enterprise), ['archive_reason' => 'تجربة'])
            ->assertRedirect(route('enterprises.show', $enterprise));

        $enterprise->refresh();
        $this->assertNotNull($enterprise->archived_at);
        $this->assertSame('تجربة', $enterprise->archive_reason);
        $this->assertTrue(AuditLog::query()->where('action', 'enterprise.archived')->exists());

        $this->actingAs($admin)
            ->get(route('enterprises.index'))
            ->assertOk()
            ->assertDontSee($enterprise->business_name);

        $this->actingAs($admin)
            ->post(route('enterprises.restore', $enterprise))
            ->assertRedirect(route('enterprises.show', $enterprise));

        $this->assertNull($enterprise->refresh()->archived_at);
    }

    public function test_non_admin_cannot_archive_enterprise(): void
    {
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $enterprise = $this->enterprise();
        $enterprise->advisors()->attach($advisor->id);

        $this->actingAs($advisor)
            ->post(route('enterprises.archive', $enterprise))
            ->assertForbidden();
    }

    public function test_admin_can_detach_member_but_not_last_member(): void
    {
        $admin = $this->admin();
        $enterprise = $this->enterprise();
        $memberA = User::factory()->create(['role' => UserRole::SmeUser]);
        $memberB = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->members()->attach([$memberA->id, $memberB->id]);

        $this->actingAs($admin)
            ->delete(route('enterprises.members.destroy', [$enterprise, $memberA]))
            ->assertRedirect();

        $this->assertFalse($enterprise->members()->whereKey($memberA->id)->exists());

        $this->actingAs($admin)
            ->delete(route('enterprises.members.destroy', [$enterprise, $memberB]))
            ->assertSessionHasErrors('user_id');

        $this->assertTrue($enterprise->members()->whereKey($memberB->id)->exists());
    }

    public function test_admin_can_detach_advisor(): void
    {
        $admin = $this->admin();
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $enterprise = $this->enterprise();
        $enterprise->advisors()->attach($advisor->id);

        $this->actingAs($admin)
            ->delete(route('enterprises.advisors.destroy', [$enterprise, $advisor]))
            ->assertRedirect();

        $this->assertFalse($enterprise->advisors()->whereKey($advisor->id)->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'enterprise.advisor_detached')->exists());
    }

    public function test_sme_cannot_detach_member_from_other_enterprise(): void
    {
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $other = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise = $this->enterprise();
        $enterprise->members()->attach([$sme->id, $other->id]);

        $this->actingAs($sme)
            ->delete(route('enterprises.members.destroy', [$enterprise, $other]))
            ->assertForbidden();
    }
}
