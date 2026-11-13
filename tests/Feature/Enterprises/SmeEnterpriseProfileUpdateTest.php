<?php

namespace Tests\Feature\Enterprises;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmeEnterpriseProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function enterpriseFor(User $sme, string $name = 'منشأتي'): Enterprise
    {
        $enterprise = Enterprise::query()->create([
            'business_name' => $name,
            'location' => 'damascus',
        ]);
        $enterprise->members()->attach($sme->id);

        return $enterprise;
    }

    public function test_sme_can_update_own_enterprise_profile_and_audit_is_recorded(): void
    {
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise = $this->enterpriseFor($sme);

        $this->actingAs($sme)->put(route('enterprises.update', $enterprise), [
            'business_name' => 'محل الياسمين',
            'location' => 'daraa',
            'sector' => 'تجارة',
            'employee_count' => 4,
            'owner_name' => 'أحمد',
            'contact_phone' => '0944000000',
            'programme_stage' => 'onboarding',
        ])->assertRedirect();

        $enterprise->refresh();
        $this->assertSame('محل الياسمين', $enterprise->business_name);
        $this->assertSame('daraa', $enterprise->location);
        $this->assertSame('تجارة', $enterprise->sector);
        $this->assertSame(4, (int) $enterprise->employee_count);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'enterprise.profile_updated',
            'actor_id' => $sme->id,
        ]);
    }

    public function test_sme_cannot_update_another_enterprise(): void
    {
        $smeA = User::factory()->create(['role' => UserRole::SmeUser]);
        $smeB = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterpriseA = $this->enterpriseFor($smeA, 'أ');
        $enterpriseB = $this->enterpriseFor($smeB, 'ب');

        $this->actingAs($smeA)->put(route('enterprises.update', $enterpriseB), [
            'business_name' => 'اختراق',
            'location' => 'damascus',
            'contact_phone' => '0999000000',
        ])->assertForbidden();

        $this->assertSame('ب', $enterpriseB->fresh()->business_name);
        $this->assertSame('أ', $enterpriseA->fresh()->business_name);
    }

    public function test_unassigned_advisor_cannot_update_enterprise(): void
    {
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $enterprise = Enterprise::query()->create([
            'business_name' => 'خارج التعيين',
            'location' => 'damascus',
        ]);

        $this->actingAs($advisor)->put(route('enterprises.update', $enterprise), [
            'business_name' => 'محاولة',
            'location' => 'damascus',
            'contact_phone' => '0911000000',
        ])->assertForbidden();
    }

    public function test_assigned_advisor_can_update_enterprise_profile(): void
    {
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $enterprise = Enterprise::query()->create([
            'business_name' => 'معينة',
            'location' => 'damascus',
        ]);
        $enterprise->advisors()->attach($advisor->id);

        $this->actingAs($advisor)->put(route('enterprises.update', $enterprise), [
            'business_name' => 'معينة محدّثة',
            'location' => 'rural_damascus',
            'contact_email' => 'owner@example.com',
        ])->assertRedirect();

        $this->assertSame('معينة محدّثة', $enterprise->fresh()->business_name);
        $this->assertSame('rural_damascus', $enterprise->fresh()->location);
    }

    public function test_profile_update_requires_contact_phone_or_email(): void
    {
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise = $this->enterpriseFor($sme);

        $this->actingAs($sme)->put(route('enterprises.update', $enterprise), [
            'business_name' => 'بدون تواصل',
            'location' => 'damascus',
        ])->assertSessionHasErrors(['contact_phone', 'contact_email']);
    }

    public function test_registration_and_programme_must_use_allowed_options(): void
    {
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise = $this->enterpriseFor($sme);

        $this->actingAs($sme)->put(route('enterprises.update', $enterprise), [
            'business_name' => 'محل الاختبار',
            'location' => 'damascus',
            'contact_phone' => '0944000000',
            'registration_status' => 'نص حر غير مسموح',
            'programme_stage' => 'مرحلة وهمية',
        ])->assertSessionHasErrors(['registration_status', 'programme_stage']);

        $this->actingAs($sme)->put(route('enterprises.update', $enterprise), [
            'business_name' => 'محل الاختبار',
            'location' => 'damascus',
            'contact_phone' => '0944000000',
            'registration_status' => 'partial',
            'programme_stage' => 'records',
        ])->assertRedirect();

        $enterprise->refresh();
        $this->assertSame('partial', $enterprise->registration_status);
        $this->assertSame('records', $enterprise->programme_stage);
    }

    public function test_edit_page_exposes_registration_and_programme_options(): void
    {
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise = $this->enterpriseFor($sme);

        $this->actingAs($sme)
            ->get(route('enterprises.edit', $enterprise))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SMEs/Edit')
                ->has('options.registration_status')
                ->has('options.programme_stage'));
    }
}
