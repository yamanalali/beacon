<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_sme_user_lands_on_sme_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::SmeUser]))
            ->get('/')->assertRedirect('/dashboard/sme');
    }

    public function test_beacon_staff_land_on_beacon_dashboard(): void
    {
        foreach ([UserRole::BeaconAdmin, UserRole::BeaconAdvisor] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/')->assertRedirect('/dashboard/beacon');
        }
    }

    public function test_mfi_and_technical_admin_keep_current_home(): void
    {
        // MFI: referrals screen arrives with PR #4. Technical admin: unchanged by request.
        foreach ([UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/')->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Dashboard/Index'));
        }
    }

    public function test_login_redirects_through_role_landing(): void
    {
        $user = User::factory()->create(['role' => UserRole::BeaconAdmin, 'password' => bcrypt('password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->get('/')->assertRedirect('/dashboard/beacon');
    }
}