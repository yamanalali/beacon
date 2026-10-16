<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Referral;
use Database\Seeders\DemoMfiSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DemoUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoMfiSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedDemo(): void
    {
        Storage::fake('local');
        $this->app['env'] = 'local';
        config(['demo.password' => 'test-demo-pass']);
        $this->seed(DemoSeeder::class);
    }

    public function test_demo_mfi_user_is_linked_and_sees_one_shared_referral(): void
    {
        $this->seedDemo();
        $mfi = DemoUsersSeeder::user(UserRole::MfiUser);

        $this->assertNotNull($mfi->mfi_institution_id);

        $this->actingAs($mfi)->get('/mfi/referrals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Referrals/MfiInbox')->has('referrals', 1));

        $referral = Referral::query()->where('purpose_ar', DemoMfiSeeder::PURPOSE)->firstOrFail();
        $this->assertTrue($referral->hasValidConsent());
    }

    public function test_demo_mfi_seeding_is_idempotent(): void
    {
        $this->seedDemo();
        $this->seed(DemoSeeder::class);

        $referral = Referral::query()->where('purpose_ar', DemoMfiSeeder::PURPOSE)->sole();
        $this->assertSame(1, $referral->consents()->count());
        $this->assertSame(1, $referral->statusChanges()->count());
    }
}
