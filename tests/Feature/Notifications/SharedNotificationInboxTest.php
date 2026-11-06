<?php

namespace Tests\Feature\Notifications;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\ComplianceDeadlineReminder;
use App\Notifications\EscalationOpenedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Page;
use Tests\TestCase;

class SharedNotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_shell_shares_system_wide_unread_count_and_recent_for_active_users(): void
    {
        $user = User::factory()->create(['role' => UserRole::SmeUser]);
        $user->forceFill(['is_active' => true])->save();

        $user->notifyNow(new ComplianceDeadlineReminder('ENT1', 'منشأة', 'متطلب', '2026-11-20'));
        $user->notifyNow(new EscalationOpenedNotification('ESC1', 'سؤال تجريبي', 'ENT1', 'منشأة'));

        $response = $this->actingAs($user)->get(route('account.show'));
        $response->assertInertia(fn (Page $page) => $page
            ->where('auth.notifications.unread_count', 2)
            ->has('auth.notifications.recent', 2));

        $types = collect($response->viewData('page')['props']['auth']['notifications']['recent'])
            ->pluck('data.type')
            ->all();
        $this->assertEqualsCanonicalizing(['compliance_deadline', 'escalation_opened'], $types);
    }

    public function test_guest_and_inactive_have_no_notification_inbox(): void
    {
        $this->get(route('login'))->assertInertia(fn (Page $page) => $page->where('auth.notifications', null));

        $user = User::factory()->create(['role' => UserRole::SmeUser]);
        $user->forceFill(['is_active' => false])->save();
        $user->notifyNow(new ComplianceDeadlineReminder('ENT1', 'منشأة', 'متطلب', '2026-11-20'));

        // Inactive users are blocked from the app shell by middleware.
        $this->actingAs($user)->get(route('account.show'))->assertForbidden();
    }
}
