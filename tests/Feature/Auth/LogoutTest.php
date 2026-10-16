<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_logout_logs_out_and_redirects_to_login(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_guest_cannot_post_logout_into_a_session(): void
    {
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}