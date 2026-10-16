<?php

namespace Tests\Feature\Administration;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersMfaNoteTest extends TestCase
{
    use RefreshDatabase;

    private function note(): string
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);

        return $this->actingAs($admin)->get(route('users.index'))->assertOk()
            ->viewData('page')['props']['mfaNote'];
    }

    public function test_note_says_off_when_mfa_disabled(): void
    {
        config(['mfa.enabled' => false]);

        $this->assertStringContainsString('غير مفعّل', $this->note());
    }

    public function test_note_says_on_when_mfa_enabled(): void
    {
        config(['mfa.enabled' => true]);

        $note = $this->note();
        $this->assertStringContainsString('مفعّل للمدراء', $note);
        $this->assertStringNotContainsString('بانتظار قرار', $note);
    }
}