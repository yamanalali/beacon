<?php

namespace Tests\Feature\Notifications;

use App\Enums\UserRole;
use App\Models\ComplianceRequirement;
use App\Models\ComplianceRequirementVersion;
use App\Models\Enterprise;
use App\Models\EnterpriseComplianceRecord;
use App\Models\User;
use App\Notifications\ComplianceDeadlineReminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ComplianceDeadlineReminderTest extends TestCase
{
    use RefreshDatabase;

    private Enterprise $enterprise;

    private User $sme;

    protected function setUp(): void
    {
        parent::setUp();
        config(['reminders.compliance.lead_days' => 7]);
        $this->travelTo('2026-10-16 09:00:00');
        $this->enterprise = Enterprise::query()->create(['business_name' => 'ورشة النور', 'location' => 'damascus']);
        $this->sme = $this->user(UserRole::SmeUser);
        $this->enterprise->members()->attach($this->sme->id);
    }

    private function user(UserRole $role, bool $active = true): User
    {
        $u = User::factory()->create(['role' => $role]);
        $u->forceFill(['is_active' => $active])->save();

        return $u;
    }

    private function record(?string $due, string $status = 'not_started', ?Enterprise $e = null): EnterpriseComplianceRecord
    {
        static $n = 0;
        $n++;
        $req = ComplianceRequirement::query()->create(['code' => 'REQ-'.$n, 'category' => 'legal']);
        $v = ComplianceRequirementVersion::query()->create([
            'compliance_requirement_id' => $req->id, 'version_number' => 1, 'title_ar' => 'السجل التجاري', 'status' => 'published',
        ]);

        return EnterpriseComplianceRecord::query()->create([
            'enterprise_id' => ($e ?? $this->enterprise)->id, 'compliance_requirement_id' => $req->id,
            'compliance_requirement_version_id' => $v->id, 'status' => $status, 'due_date' => $due,
        ]);
    }

    public function test_reminds_sme_members_once_within_window_only(): void
    {
        $this->record('2026-10-20');               // in window
        $this->record('2026-10-30');               // too far
        $this->record('2026-10-10');               // past
        $this->record('2026-10-18', 'completed');  // done
        $this->record(null);                       // no due date

        $this->artisan('reminders:compliance-deadlines')->assertSuccessful();
        $this->artisan('reminders:compliance-deadlines')->assertSuccessful(); // idempotent

        $this->assertSame(1, $this->sme->notifications()->count());
        $data = $this->sme->notifications()->first()->data;
        $this->assertSame('compliance_deadline', $data['type']);
        $this->assertSame($this->enterprise->public_id, $data['enterprise_public_id']);
        $this->assertStringContainsString('السجل التجاري', $data['message']);
        $this->assertArrayNotHasKey('enterprise_id', $data);
    }

    public function test_only_database_channel_is_used(): void
    {
        Notification::fake();
        $this->record('2026-10-20');
        $this->artisan('reminders:compliance-deadlines');
        Notification::assertSentTo($this->sme, ComplianceDeadlineReminder::class, fn ($n, $channels) => $channels === ['database']);
    }

    public function test_recipients_are_limited_by_enterprise_access_and_role(): void
    {
        $inactiveSme = $this->user(UserRole::SmeUser, false);
        $this->enterprise->members()->attach($inactiveSme->id);
        $otherSme = $this->user(UserRole::SmeUser);
        $advisor = $this->user(UserRole::BeaconAdvisor);
        $this->enterprise->advisors()->attach($advisor->id);
        $admin = $this->user(UserRole::BeaconAdmin);
        $mfi = $this->user(UserRole::MfiUser);
        $tech = $this->user(UserRole::TechnicalAdmin);
        $this->enterprise->members()->attach([$mfi->id, $tech->id]); // even if mis-attached

        $this->record('2026-10-20');
        $this->artisan('reminders:compliance-deadlines');

        $this->assertSame(1, $this->sme->notifications()->count());
        foreach ([$inactiveSme, $otherSme, $advisor, $admin, $mfi, $tech] as $u) {
            $this->assertSame(0, $u->notifications()->count(), $u->role->value);
        }

        // Interim TODO toggle: assigned advisors only when enabled.
        config(['reminders.compliance.notify_assigned_advisors' => true]);
        $unassigned = $this->user(UserRole::BeaconAdvisor);
        $this->artisan('reminders:compliance-deadlines');
        $this->assertSame(1, $advisor->notifications()->count());
        $this->assertSame(0, $unassigned->notifications()->count());
        $this->assertSame(1, $this->sme->notifications()->count());
    }

    public function test_command_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command ?? '', 'reminders:compliance-deadlines'));
        $this->assertCount(1, $events);
        $this->assertSame('0 8 * * *', $events->first()->expression);
    }

    public static function allRoles(): array
    {
        return array_map(fn ($r) => [$r], UserRole::cases());
    }

    /** @dataProvider allRoles */
    public function test_each_role_lists_and_marks_only_own_notifications(UserRole $role): void
    {
        $this->record('2026-10-20');
        $this->artisan('reminders:compliance-deadlines');
        $smeNotification = $this->sme->notifications()->first();

        $user = $role === UserRole::SmeUser ? $this->user(UserRole::SmeUser) : $this->user($role);
        $this->actingAs($user)->getJson(route('notifications.index'))
            ->assertOk()->assertJsonPath('unread_count', 0)->assertJsonPath('notifications.total', 0);
        $this->postJson(route('notifications.read', $smeNotification->id))->assertNotFound();
        $this->assertNull($smeNotification->fresh()->read_at);
    }

    public function test_owner_lists_and_marks_read(): void
    {
        $this->record('2026-10-20');
        $this->artisan('reminders:compliance-deadlines');
        $id = $this->sme->notifications()->first()->id;

        $this->actingAs($this->sme)->getJson(route('notifications.index'))
            ->assertOk()->assertJsonPath('unread_count', 1)->assertJsonPath('notifications.data.0.id', $id);
        $this->postJson(route('notifications.read', $id))->assertOk();
        $this->getJson(route('notifications.index'))->assertJsonPath('unread_count', 0);
    }

    public function test_guest_and_inactive_are_blocked(): void
    {
        $this->getJson(route('notifications.index'))->assertUnauthorized();
        $this->sme->forceFill(['is_active' => false])->save();
        $this->actingAs($this->sme)->getJson(route('notifications.index'))->assertForbidden();
    }
}
