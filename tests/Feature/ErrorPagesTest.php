<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_403_renders_custom_page_with_home_link(): void
    {
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);

        $this->actingAs($sme)->get('/operations/security')
            ->assertForbidden()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Errors/ErrorPage')
                ->where('status', 403)
                ->where('homeUrl', route('home'))
                ->where('isGuest', false));
    }

    public function test_404_renders_custom_page_for_guest_with_login_link(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Errors/ErrorPage')
                ->where('status', 404)
                ->where('homeUrl', route('login'))
                ->where('isGuest', true));
    }

    public function test_500_hides_technical_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/__boom', fn () => throw new RuntimeException('secret-internal-detail'));

        $response = $this->get('/__boom')->assertStatus(500)
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Errors/ErrorPage')->where('status', 500));

        $this->assertStringNotContainsString('secret-internal-detail', $response->getContent());
    }

    public function test_json_requests_keep_json_errors(): void
    {
        $this->getJson('/this-page-does-not-exist')->assertNotFound()->assertHeader('content-type', 'application/json');
    }
}
