<?php

namespace Tests\Feature\Operations;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MfaChecklistTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function mfaItem(User $viewer): array
    {
        $items = $this->actingAs($viewer)->get(route('operations.security'))->assertOk()
            ->viewData('page')['props']['items'];

        return collect($items)->firstWhere('key', 'admin_mfa');
    }

    private function enroll(User $user, string $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP'): void
    {
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
    }

    public function test_item_reports_disabled_and_stays_pending_decision(): void
    {
        $tech = User::factory()->create(['role' => UserRole::TechnicalAdmin]);

        $item = $this->mfaItem($tech);

        $this->assertSame('pending_decision', $item['status']);
        $this->assertFalse($item['mfa_enabled']);
        $this->assertStringContainsString('BEACON_ADMIN_MFA=false', $item['detail_ar']);
    }

    public function test_item_counts_only_confirmed_admin_enrollments_without_secrets(): void
    {
        config(['mfa.enabled' => true]);
        $secret = 'KRSXG5CTMVRXEZLUKRSXG5CTMVRXEZLU';
        $tech = User::factory()->create(['role' => UserRole::TechnicalAdmin]);
        $this->enroll($tech, $secret);
        User::factory()->create(['role' => UserRole::BeaconAdmin]);
        User::factory()->create(['role' => UserRole::BeaconAdmin])
            ->forceFill(['two_factor_secret' => 'PENDINGPENDINGPENDINGPENDINGPEND'])->save();
        $this->enroll(User::factory()->create(['role' => UserRole::SmeUser]));

        $response = $this->actingAs($tech)->get(route('operations.security'));
        $item = collect($response->viewData('page')['props']['items'])->firstWhere('key', 'admin_mfa');

        $this->assertSame('pending_decision', $item['status']);
        $this->assertTrue($item['mfa_enabled']);
        $this->assertSame(1, $item['mfa_enrolled_admins']);
        $this->assertSame(3, $item['mfa_total_admins']);
        $this->assertStringContainsString('1 من 3', $item['detail_ar']);

        $body = json_encode($response->viewData('page'));
        $this->assertStringNotContainsString($secret, $body);
        $this->assertStringNotContainsString('two_factor_secret', $body);
        $this->assertStringNotContainsString('recovery', $body);
    }
}
