<?php

namespace Tests\Feature\Enterprises;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_sme_a_cannot_view_sme_b_enterprise_url(): void
    {
        $smeA = User::factory()->create(['role' => UserRole::SmeUser]);
        $smeB = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterpriseA = $this->enterpriseFor($smeA, 'منشأة أ');
        $enterpriseB = $this->enterpriseFor($smeB, 'منشأة ب');

        $this->actingAs($smeA)
            ->get('/enterprises/'.$enterpriseB->public_id)
            ->assertForbidden();

        $this->actingAs($smeA)
            ->get('/enterprises/'.$enterpriseA->public_id)
            ->assertOk();
    }

    public function test_advisor_cannot_view_unassigned_enterprise(): void
    {
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $enterprise = Enterprise::query()->create([
            'business_name' => 'منشأة خارج التعيين',
            'location' => 'daraa',
        ]);

        $this->actingAs($advisor)
            ->get('/enterprises/'.$enterprise->public_id)
            ->assertForbidden();
    }

    public function test_assigned_advisor_can_view(): void
    {
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $enterprise = Enterprise::query()->create([
            'business_name' => 'منشأة معينة',
            'location' => 'damascus',
        ]);
        $enterprise->advisors()->attach($advisor->id);

        $this->actingAs($advisor)
            ->get('/enterprises/'.$enterprise->public_id)
            ->assertOk();
    }

    public function test_beacon_admin_can_view(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $enterprise = Enterprise::query()->create([
            'business_name' => 'منشأة الإدارة',
            'location' => 'rural_damascus',
        ]);

        $this->actingAs($admin)
            ->get('/enterprises/'.$enterprise->public_id)
            ->assertOk();
    }

    public function test_mfi_user_receives_403_on_show_and_index(): void
    {
        $mfi = User::factory()->create(['role' => UserRole::MfiUser]);
        $enterprise = Enterprise::query()->create([
            'business_name' => 'منشأة للمؤسسة',
            'location' => 'damascus',
        ]);

        $this->actingAs($mfi)->get('/enterprises')->assertForbidden();
        $this->actingAs($mfi)->get('/enterprises/'.$enterprise->public_id)->assertForbidden();
    }

    public function test_technical_admin_receives_403_on_show(): void
    {
        $technical = User::factory()->create(['role' => UserRole::TechnicalAdmin]);
        $enterprise = Enterprise::query()->create([
            'business_name' => 'منشأة تقنية',
            'location' => 'damascus',
        ]);

        $this->actingAs($technical)
            ->get('/enterprises/'.$enterprise->public_id)
            ->assertForbidden();
    }

    public function test_sme_index_does_not_list_another_enterprise(): void
    {
        $smeA = User::factory()->create(['role' => UserRole::SmeUser]);
        $smeB = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterpriseA = $this->enterpriseFor($smeA, 'ورشة الأمل');
        $this->enterpriseFor($smeB, 'مخبز السر');

        $this->actingAs($smeA)
            ->get('/enterprises')
            ->assertRedirect('/enterprises/'.$enterpriseA->public_id);
    }

    public function test_creating_an_enterprise_does_not_require_employee_count(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);

        $this->actingAs($admin)
            ->post('/enterprises', [
                'business_name' => 'ورشة النور',
                'location' => 'damascus',
                'contact_phone' => '0999000111',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('enterprises', [
            'business_name' => 'ورشة النور',
            'employee_count' => null,
        ]);
    }

    public function test_profile_update_writes_audit_row(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $enterprise = Enterprise::query()->create([
            'business_name' => 'قبل التعديل',
            'location' => 'damascus',
            'contact_phone' => '0999000111',
        ]);

        $this->actingAs($admin)
            ->put('/enterprises/'.$enterprise->public_id, [
                'business_name' => 'بعد التعديل',
                'location' => 'damascus',
                'contact_phone' => '0999000111',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'enterprise.profile_updated',
            'target_type' => $enterprise->getMorphClass(),
            'target_id' => $enterprise->id,
        ]);
    }

    public function test_direct_url_uses_public_id_and_wrong_user_cannot_guess_it(): void
    {
        $smeA = User::factory()->create(['role' => UserRole::SmeUser]);
        $smeB = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterpriseB = $this->enterpriseFor($smeB, 'منشأة مخفية');

        $this->assertNotSame((string) $enterpriseB->id, $enterpriseB->public_id);

        $this->actingAs($smeA)
            ->get('/enterprises/'.$enterpriseB->public_id)
            ->assertForbidden();

        $this->actingAs($smeA)
            ->get('/enterprises/'.$enterpriseB->id)
            ->assertNotFound();
    }

    private function enterpriseFor(User $member, string $name): Enterprise
    {
        $enterprise = Enterprise::query()->create([
            'business_name' => $name,
            'location' => 'damascus',
            'contact_phone' => '0999000000',
        ]);
        $enterprise->members()->attach($member->id);

        return $enterprise;
    }
}
