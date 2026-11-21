<?php

namespace Tests\Feature\Advisory;

use App\Enums\AdvisoryRecordStatus;
use App\Enums\UserRole;
use App\Models\AdvisoryActivity;
use App\Models\Enterprise;
use App\Models\FollowUpAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvisoryMutationTest extends TestCase
{
    use RefreshDatabase;

    private function enterprise(string $name = 'ورشة النور'): Enterprise
    {
        return Enterprise::query()->create(['business_name' => $name, 'location' => 'damascus']);
    }

    private function advisorFor(Enterprise $enterprise): User
    {
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $enterprise->advisors()->attach($advisor->id);

        return $advisor;
    }

    private function activity(Enterprise $enterprise, User $creator): AdvisoryActivity
    {
        return AdvisoryActivity::query()->create([
            'enterprise_id' => $enterprise->id,
            'activity_date' => '2026-10-16',
            'notes' => 'ملاحظة أولية',
            'status' => AdvisoryRecordStatus::Open,
            'created_by' => $creator->id,
        ]);
    }

    private function followUp(Enterprise $enterprise, User $creator, AdvisoryRecordStatus $status = AdvisoryRecordStatus::Open): FollowUpAction
    {
        return FollowUpAction::query()->create([
            'enterprise_id' => $enterprise->id,
            'description' => 'إرسال الكشف',
            'status' => $status,
            'created_by' => $creator->id,
        ]);
    }

    public function test_assigned_advisor_can_update_activity_and_audit_is_written(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorFor($enterprise);
        $activity = $this->activity($enterprise, $advisor);

        $this->actingAs($advisor)
            ->put(route('advisory-activities.update', $activity), [
                'activity_date' => '2026-10-20',
                'notes' => 'ملاحظة معدّلة',
                'target_date' => '2026-11-05',
            ])
            ->assertRedirect(route('enterprises.advisory.index', $enterprise))
            ->assertSessionHas('success', ui('flash.advisory_updated'));

        $this->assertDatabaseHas('advisory_activities', [
            'id' => $activity->id,
            'notes' => 'ملاحظة معدّلة',
        ]);
        $this->assertSame('2026-10-20', $activity->fresh()->activity_date->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'advisory.updated', 'actor_id' => $advisor->id]);
    }

    public function test_update_requires_activity_date(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorFor($enterprise);
        $activity = $this->activity($enterprise, $advisor);

        $this->actingAs($advisor)
            ->put(route('advisory-activities.update', $activity), ['activity_date' => ''])
            ->assertSessionHasErrors('activity_date');
    }

    public function test_update_rejects_responsible_user_outside_the_enterprise(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorFor($enterprise);
        $activity = $this->activity($enterprise, $advisor);
        $outsider = User::factory()->create(['role' => UserRole::SmeUser]);

        $this->actingAs($advisor)
            ->put(route('advisory-activities.update', $activity), [
                'activity_date' => '2026-10-20',
                'responsible_user_id' => $outsider->id,
            ])
            ->assertSessionHasErrors('responsible_user_id');
    }

    public function test_admin_can_delete_activity_and_linked_follow_up_survives(): void
    {
        $enterprise = $this->enterprise();
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $activity = $this->activity($enterprise, $admin);
        $followUp = $this->followUp($enterprise, $admin);
        $followUp->update(['advisory_activity_id' => $activity->id]);

        $this->actingAs($admin)
            ->delete(route('advisory-activities.destroy', $activity))
            ->assertRedirect(route('enterprises.advisory.index', $enterprise))
            ->assertSessionHas('success', ui('flash.advisory_deleted'));

        $this->assertDatabaseMissing('advisory_activities', ['id' => $activity->id]);
        $this->assertDatabaseHas('follow_up_actions', ['id' => $followUp->id, 'advisory_activity_id' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'advisory.deleted', 'actor_id' => $admin->id]);
    }

    public function test_unassigned_advisor_sme_mfi_and_technical_admin_cannot_mutate_activity(): void
    {
        $enterprise = $this->enterprise();
        $owner = $this->advisorFor($enterprise);
        $activity = $this->activity($enterprise, $owner);
        $payload = ['activity_date' => '2026-12-01', 'notes' => 'اختراق'];

        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->members()->attach($sme->id);

        $actors = [
            User::factory()->create(['role' => UserRole::BeaconAdvisor]),
            $sme,
            User::factory()->create(['role' => UserRole::MfiUser]),
            User::factory()->create(['role' => UserRole::TechnicalAdmin]),
        ];

        foreach ($actors as $actor) {
            $this->actingAs($actor)->put(route('advisory-activities.update', $activity), $payload)->assertForbidden();
            $this->actingAs($actor)->delete(route('advisory-activities.destroy', $activity))->assertForbidden();
        }

        $this->assertDatabaseHas('advisory_activities', ['id' => $activity->id, 'notes' => 'ملاحظة أولية']);
    }

    public function test_activity_cannot_be_mutated_on_archived_enterprise(): void
    {
        $enterprise = $this->enterprise();
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $activity = $this->activity($enterprise, $admin);
        $enterprise->forceFill(['archived_at' => now()])->save();

        $this->actingAs($admin)->delete(route('advisory-activities.destroy', $activity))->assertForbidden();
        $this->assertDatabaseHas('advisory_activities', ['id' => $activity->id]);
    }

    public function test_advisor_can_update_open_follow_up(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorFor($enterprise);
        $followUp = $this->followUp($enterprise, $advisor);

        $this->actingAs($advisor)
            ->put(route('follow-ups.update', $followUp), [
                'description' => 'إرسال الكشف المحدّث',
                'target_date' => '2026-12-01',
            ])
            ->assertRedirect(route('enterprises.advisory.index', $enterprise))
            ->assertSessionHas('success', ui('flash.follow_up_updated'));

        $this->assertDatabaseHas('follow_up_actions', ['id' => $followUp->id, 'description' => 'إرسال الكشف المحدّث']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'follow_up.updated', 'actor_id' => $advisor->id]);
    }

    public function test_completed_follow_up_cannot_be_edited_until_reopened(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorFor($enterprise);
        $followUp = $this->followUp($enterprise, $advisor, AdvisoryRecordStatus::Completed);

        $this->actingAs($advisor)
            ->put(route('follow-ups.update', $followUp), ['description' => 'تعديل مرفوض'])
            ->assertSessionHasErrors('description');

        $this->assertDatabaseHas('follow_up_actions', ['id' => $followUp->id, 'description' => 'إرسال الكشف']);
    }

    public function test_advisor_can_reopen_completed_follow_up(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorFor($enterprise);
        $followUp = $this->followUp($enterprise, $advisor, AdvisoryRecordStatus::Completed);

        $this->actingAs($advisor)
            ->post(route('follow-ups.reopen', $followUp))
            ->assertRedirect(route('enterprises.advisory.index', $enterprise))
            ->assertSessionHas('success', ui('flash.follow_up_reopened'));

        $this->assertDatabaseHas('follow_up_actions', ['id' => $followUp->id, 'status' => 'open']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'follow_up.reopened', 'actor_id' => $advisor->id]);
    }

    public function test_reopening_an_open_follow_up_is_rejected(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorFor($enterprise);
        $followUp = $this->followUp($enterprise, $advisor);

        $this->actingAs($advisor)
            ->post(route('follow-ups.reopen', $followUp))
            ->assertSessionHasErrors('status');
    }

    public function test_advisor_can_delete_follow_up(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorFor($enterprise);
        $followUp = $this->followUp($enterprise, $advisor);

        $this->actingAs($advisor)
            ->delete(route('follow-ups.destroy', $followUp))
            ->assertRedirect(route('enterprises.advisory.index', $enterprise))
            ->assertSessionHas('success', ui('flash.follow_up_deleted'));

        $this->assertDatabaseMissing('follow_up_actions', ['id' => $followUp->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'follow_up.deleted', 'actor_id' => $advisor->id]);
    }

    public function test_follow_up_mutations_are_forbidden_for_everyone_else(): void
    {
        $enterprise = $this->enterprise();
        $owner = $this->advisorFor($enterprise);
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->members()->attach($sme->id);
        $completed = $this->followUp($enterprise, $owner, AdvisoryRecordStatus::Completed);
        $open = $this->followUp($enterprise, $owner);
        // A responsible SME member may complete, but must not edit/delete/reopen.
        $open->update(['responsible_user_id' => $sme->id]);

        $actors = [
            User::factory()->create(['role' => UserRole::BeaconAdvisor]),
            $sme,
            User::factory()->create(['role' => UserRole::MfiUser]),
            User::factory()->create(['role' => UserRole::TechnicalAdmin]),
        ];

        foreach ($actors as $actor) {
            $this->actingAs($actor)->put(route('follow-ups.update', $open), ['description' => 'x'])->assertForbidden();
            $this->actingAs($actor)->post(route('follow-ups.reopen', $completed))->assertForbidden();
            $this->actingAs($actor)->delete(route('follow-ups.destroy', $open))->assertForbidden();
        }

        $this->assertDatabaseHas('follow_up_actions', ['id' => $open->id, 'description' => 'إرسال الكشف']);
        $this->assertDatabaseHas('follow_up_actions', ['id' => $completed->id, 'status' => 'completed']);
    }

    public function test_guests_are_redirected(): void
    {
        $enterprise = $this->enterprise();
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $followUp = $this->followUp($enterprise, $admin);
        $activity = $this->activity($enterprise, $admin);

        $this->delete(route('follow-ups.destroy', $followUp))->assertRedirect(route('login'));
        $this->delete(route('advisory-activities.destroy', $activity))->assertRedirect(route('login'));
    }
}
