<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', EnsureUserHasRole::class.':beacon_admin'])
            ->get('/_test/beacon-admin', fn () => 'ok');
    }

    public function test_sme_user_receives_403_on_beacon_admin_route(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SmeUser,
        ]);

        $this->actingAs($user)
            ->get('/_test/beacon-admin')
            ->assertStatus(403);
    }

    public function test_active_beacon_admin_receives_200(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/_test/beacon-admin')
            ->assertStatus(200);
    }

    public function test_inactive_beacon_admin_receives_403(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->get('/_test/beacon-admin')
            ->assertStatus(403);
    }

    public function test_mfi_user_receives_403_on_beacon_admin_route(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::MfiUser,
        ]);

        $this->actingAs($user)
            ->get('/_test/beacon-admin')
            ->assertStatus(403);
    }

    public function test_technical_admin_receives_403_on_beacon_admin_route(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::TechnicalAdmin,
        ]);

        $this->actingAs($user)
            ->get('/_test/beacon-admin')
            ->assertStatus(403);
    }

    public function test_mass_assignment_does_not_change_role_or_active_status(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SmeUser,
            'is_active' => true,
        ]);

        $user->update([
            'role' => 'beacon_admin',
            'is_active' => false,
        ]);

        $user->refresh();

        $this->assertSame(UserRole::SmeUser, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->isActive());
    }

    public function test_update_role_policy_allows_only_active_beacon_admin_for_another_user(): void
    {
        $sme = User::factory()->create([
            'role' => UserRole::SmeUser,
        ]);
        $technicalAdmin = User::factory()->create([
            'role' => UserRole::TechnicalAdmin,
        ]);
        $beaconAdmin = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
            'is_active' => true,
        ]);
        $other = User::factory()->create([
            'role' => UserRole::SmeUser,
        ]);

        $this->assertFalse($sme->can('updateRole', $other));
        $this->assertFalse($technicalAdmin->can('updateRole', $other));
        $this->assertTrue($beaconAdmin->can('updateRole', $other));
        $this->assertFalse($beaconAdmin->can('updateRole', $beaconAdmin));
    }
}
