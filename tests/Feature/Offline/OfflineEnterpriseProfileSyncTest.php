<?php

namespace Tests\Feature\Offline;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use App\Support\OfflinePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineEnterpriseProfileSyncTest extends TestCase
{
    use RefreshDatabase;

    private function headers(User $user, string $key, ?string $versions = null): array
    {
        $headers = [
            'X-Beacon-Operation-Key' => $key,
            'X-Beacon-Offline-Scope' => OfflinePolicy::scope($user),
            'X-Beacon-Offline-Replay' => '1',
        ];
        if ($versions !== null) {
            $headers['X-Beacon-Offline-Versions'] = $versions;
        }

        return $headers;
    }

    private function profilePayload(string $name = 'متجر أوفلاين'): array
    {
        return [
            'business_name' => $name,
            'location' => 'damascus',
            'contact_phone' => '0944111222',
            'sector' => 'خدمات',
        ];
    }

    public function test_offline_profile_update_is_idempotent_and_rejects_changed_payload(): void
    {
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise = Enterprise::query()->create([
            'business_name' => 'قديم',
            'location' => 'damascus',
        ]);
        $enterprise->members()->attach($sme->id);

        $versions = $this->actingAs($sme)
            ->get(route('enterprises.show', $enterprise))
            ->headers
            ->get('X-Beacon-Offline-Versions');
        $this->assertNotEmpty($versions);

        $key = (string) Str::uuid();
        $url = route('enterprises.update', $enterprise);
        $payload = $this->profilePayload();

        $this->putJson($url, $payload, $this->headers($sme, $key, $versions))
            ->assertOk()
            ->assertJson(['synced' => true, 'operation_key' => $key]);

        $this->assertSame('متجر أوفلاين', $enterprise->fresh()->business_name);

        $this->putJson($url, $payload, $this->headers($sme, $key, $versions))
            ->assertOk()
            ->assertJson(['synced' => true]);

        $this->assertDatabaseCount('offline_operations', 1);

        $this->putJson($url, $this->profilePayload('اسم مختلف'), $this->headers($sme, $key, $versions))
            ->assertStatus(409);

        $this->assertSame('متجر أوفلاين', $enterprise->fresh()->business_name);
    }

    public function test_removed_membership_blocks_live_and_offline_profile_update(): void
    {
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise = Enterprise::query()->create([
            'business_name' => 'قديم',
            'location' => 'damascus',
        ]);
        $enterprise->members()->attach($sme->id);

        $versions = $this->actingAs($sme)
            ->get(route('enterprises.show', $enterprise))
            ->headers
            ->get('X-Beacon-Offline-Versions');

        $enterprise->members()->detach($sme->id);

        $this->actingAs($sme)->put(route('enterprises.update', $enterprise), $this->profilePayload())
            ->assertForbidden();

        $this->actingAs($sme)->putJson(
            route('enterprises.update', $enterprise),
            $this->profilePayload(),
            $this->headers($sme, (string) Str::uuid(), $versions)
        )->assertForbidden();

        $this->assertSame('قديم', $enterprise->fresh()->business_name);
        $this->assertDatabaseCount('offline_operations', 0);
    }
}
