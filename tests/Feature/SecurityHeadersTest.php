<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_pages_allow_same_origin_camera_and_microphone(): void
    {
        $user = User::factory()->create(['role' => UserRole::SmeUser]);

        $response = $this->actingAs($user)->get('/dashboard/sme')->assertOk();

        $response->assertHeader(
            'Permissions-Policy',
            'camera=(self), microphone=(self), geolocation=()'
        );
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
