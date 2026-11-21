<?php

namespace Tests\Feature\Notifications;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\ComplianceDeadlineReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationManagementTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user): string
    {
        $user->notifyNow(new ComplianceDeadlineReminder('ENT1', 'منشأة', 'متطلب', '2026-11-20'));

        return $user->notifications()->latest()->firstOrFail()->id;
    }

    public function test_user_can_delete_own_notification(): void
    {
        $user = User::factory()->create(['role' => UserRole::SmeUser]);
        $id = $this->notify($user);

        $this->actingAs($user)
            ->from('/notifications/page')
            ->delete(route('notifications.destroy', $id))
            ->assertRedirect('/notifications/page')
            ->assertSessionHas('success', ui('flash.notification_deleted'));

        $this->assertDatabaseMissing('notifications', ['id' => $id]);
    }

    public function test_user_cannot_delete_someone_elses_notification(): void
    {
        $owner = User::factory()->create(['role' => UserRole::SmeUser]);
        $intruder = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $id = $this->notify($owner);

        $this->actingAs($intruder)->delete(route('notifications.destroy', $id))->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $id]);
    }

    public function test_deleting_unknown_or_malformed_id_is_not_found(): void
    {
        $user = User::factory()->create(['role' => UserRole::SmeUser]);

        $this->actingAs($user)->delete(route('notifications.destroy', (string) Str::uuid()))->assertNotFound();
        $this->actingAs($user)->delete('/notifications/not-a-uuid')->assertNotFound();
    }

    public function test_read_all_marks_only_current_users_notifications(): void
    {
        $user = User::factory()->create(['role' => UserRole::SmeUser]);
        $other = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->notify($user);
        $this->notify($user);
        $this->notify($other);

        $this->actingAs($user)
            ->post(route('notifications.read-all'))
            ->assertRedirect()
            ->assertSessionHas('success', ui('flash.notifications_marked_read'));

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(2, $user->notifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_inactive_user_and_guest_cannot_use_new_endpoints(): void
    {
        $user = User::factory()->create(['role' => UserRole::SmeUser]);
        $id = $this->notify($user);

        $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
        $this->delete(route('notifications.destroy', $id))->assertRedirect(route('login'));

        $user->forceFill(['is_active' => false])->save();
        $this->actingAs($user)->post(route('notifications.read-all'))->assertForbidden();
        $this->actingAs($user)->delete(route('notifications.destroy', $id))->assertForbidden();
        $this->assertDatabaseHas('notifications', ['id' => $id]);
    }
}
