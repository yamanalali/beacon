<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard/beacon')->assertRedirect('/login');
        $this->get('/dashboard/sme')->assertRedirect('/login');
    }

    public function test_beacon_staff_can_view_beacon_dashboard_with_stub_props(): void
    {
        foreach ([UserRole::BeaconAdmin, UserRole::BeaconAdvisor] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/dashboard/beacon')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Dashboard/Beacon')
                    ->where('indicators', [])
                    ->where('exportUrl', null));
        }
    }

    public function test_non_beacon_roles_cannot_view_beacon_dashboard(): void
    {
        foreach ([UserRole::SmeUser, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/dashboard/beacon')->assertForbidden();
        }
    }

    public function test_sme_user_can_view_sme_dashboard(): void
    {
        $user = User::factory()->create(['role' => UserRole::SmeUser]);

        $this->actingAs($user)->get('/dashboard/sme')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Sme')
                ->where('readinessStatus', null));
    }

    public function test_other_roles_cannot_view_sme_dashboard(): void
    {
        foreach ([UserRole::BeaconAdmin, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/dashboard/sme')->assertForbidden();
        }
    }
}