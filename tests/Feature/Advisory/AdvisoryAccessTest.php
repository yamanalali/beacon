<?php

namespace Tests\Feature\Advisory;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FollowUpAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvisoryAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_sme_cannot_post_advisory(): void
    {
        $enterprise = Enterprise::query()->create([
            'business_name' => 'منشأة تجريبية',
            'location' => 'damascus',
        ]);
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->members()->attach($sme->id);

        $this->actingAs($sme)
            ->post(route('enterprises.advisory.store', $enterprise), [
                'activity_date' => '2026-10-16',
                'notes' => 'محاولة تسجيل',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('advisory_activities', 0);
    }

    public function test_unassigned_advisor_receives_403(): void
    {
        $enterprise = Enterprise::query()->create([
            'business_name' => 'منشأة أخرى',
            'location' => 'daraa',
        ]);
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);

        $this->actingAs($advisor)
            ->post(route('enterprises.advisory.store', $enterprise), [
                'activity_date' => '2026-10-16',
            ])
            ->assertForbidden();

        $this->actingAs($advisor)
            ->get(route('enterprises.advisory.index', $enterprise))
            ->assertForbidden();
    }

    public function test_assigned_advisor_can_record_activity_and_optional_follow_up(): void
    {
        $enterprise = Enterprise::query()->create([
            'business_name' => 'ورشة النور',
            'location' => 'rural_damascus',
        ]);
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $enterprise->advisors()->attach($advisor->id);

        $this->actingAs($advisor)
            ->post(route('enterprises.advisory.store', $enterprise), [
                'activity_date' => '2026-10-16',
                'notes' => 'مراجعة السجلات',
                'follow_up_description' => 'إرسال كشف المبيعات',
                'target_date' => '2026-11-01',
            ])
            ->assertRedirect(route('enterprises.advisory.index', $enterprise));

        $this->assertDatabaseHas('advisory_activities', [
            'enterprise_id' => $enterprise->id,
            'notes' => 'مراجعة السجلات',
            'status' => 'open',
            'created_by' => $advisor->id,
        ]);

        $this->assertDatabaseHas('follow_up_actions', [
            'enterprise_id' => $enterprise->id,
            'description' => 'إرسال كشف المبيعات',
            'status' => 'open',
            'created_by' => $advisor->id,
        ]);
    }

    public function test_mfi_user_receives_403(): void
    {
        $enterprise = Enterprise::query()->create([
            'business_name' => 'منشأة تمويل',
            'location' => 'damascus',
        ]);
        $mfi = User::factory()->create(['role' => UserRole::MfiUser]);

        $this->actingAs($mfi)
            ->get(route('enterprises.advisory.index', $enterprise))
            ->assertForbidden();

        $this->actingAs($mfi)
            ->post(route('enterprises.advisory.store', $enterprise), [
                'activity_date' => '2026-10-16',
            ])
            ->assertForbidden();
    }

    public function test_technical_admin_receives_403(): void
    {
        $enterprise = Enterprise::query()->create([
            'business_name' => 'منشأة تقنية',
            'location' => 'damascus',
        ]);
        $technicalAdmin = User::factory()->create(['role' => UserRole::TechnicalAdmin]);

        $this->actingAs($technicalAdmin)
            ->get(route('enterprises.advisory.index', $enterprise))
            ->assertForbidden();

        $this->actingAs($technicalAdmin)
            ->post(route('enterprises.advisory.store', $enterprise), [
                'activity_date' => '2026-10-16',
            ])
            ->assertForbidden();
    }

    public function test_sme_of_enterprise_can_view_advisory_page(): void
    {
        $enterprise = Enterprise::query()->create([
            'business_name' => 'محل الياسمين',
            'location' => 'damascus',
        ]);
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->members()->attach($sme->id);

        $this->actingAs($sme)
            ->get(route('enterprises.advisory.index', $enterprise))
            ->assertOk();
    }

    public function test_completing_a_follow_up_sets_status_completed_and_keeps_the_row(): void
    {
        $enterprise = Enterprise::query()->create([
            'business_name' => 'مخبز الأمل',
            'location' => 'daraa',
        ]);
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $enterprise->advisors()->attach($advisor->id);

        $this->actingAs($advisor)
            ->post(route('enterprises.advisory.store', $enterprise), [
                'activity_date' => '2026-10-16',
                'follow_up_description' => 'تجهيز الرخصة',
            ])
            ->assertRedirect();

        $followUp = FollowUpAction::query()->firstOrFail();

        $this->actingAs($advisor)
            ->post(route('follow-ups.complete', $followUp))
            ->assertRedirect(route('enterprises.advisory.index', $enterprise));

        $this->assertDatabaseHas('follow_up_actions', [
            'id' => $followUp->id,
            'status' => 'completed',
            'description' => 'تجهيز الرخصة',
        ]);
        $this->assertDatabaseCount('follow_up_actions', 1);
        $this->assertNotNull(FollowUpAction::query()->find($followUp->id));
    }
}
