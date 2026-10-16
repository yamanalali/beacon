<?php

namespace Tests\Feature\Navigation;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\MfiInstitution;
use App\Models\User;
use App\Navigation\NavigationBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED = [
        'sme_user' => ['dashboard.sme', 'enterprises', 'transactions', 'documents', 'reports', 'readiness', 'compliance', 'referrals', 'ai.assistant', 'ai.escalations', 'ai.knowledge', 'notifications', 'account', 'account.sessions'],
        'beacon_advisor' => ['dashboard.beacon', 'enterprises', 'transactions', 'documents', 'reports', 'ai.assistant', 'ai.escalations', 'ai.knowledge', 'notifications', 'account', 'account.sessions'],
        'beacon_admin' => ['dashboard.beacon', 'enterprises', 'transactions', 'documents', 'reports', 'ai.assistant', 'ai.escalations', 'ai.knowledge', 'notifications', 'admin', 'admin.users', 'admin.audit-logs', 'admin.compliance', 'admin.readiness', 'admin.mfis', 'admin.knowledge', 'operations.data-control', 'account', 'account.sessions'],
        'mfi_user' => ['mfi.referrals', 'notifications', 'account', 'account.sessions'],
        'technical_admin' => ['notifications', 'operations.security', 'operations.backups', 'operations.data-control', 'account', 'account.sessions'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public static function roles(): array
    {
        return array_map(fn (UserRole $r) => [$r], array_combine(
            array_map(fn (UserRole $r) => $r->value, UserRole::cases()),
            UserRole::cases(),
        ));
    }

    private function makeUser(UserRole $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $attributes = ['is_active' => true];

        if ($role === UserRole::MfiUser) {
            $attributes['mfi_institution_id'] = MfiInstitution::query()->create(['name_ar' => 'مؤسسة']) ->id;
        }

        $user->forceFill($attributes)->save();

        if ($role === UserRole::SmeUser) {
            Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus'])->members()->attach($user->id);
        }

        return $user->fresh();
    }

    private function navigation(User $user): array
    {
        $page = $this->actingAs($user)->get('/account')->assertOk()->viewData('page');

        return $page['props']['auth']['navigation'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('roles')]
    public function test_navigation_matches_pages_the_role_can_open(UserRole $role): void
    {
        $user = $this->makeUser($role);
        $nav = $this->navigation($user);
        $keys = array_column($nav, 'key');

        $this->assertEqualsCanonicalizing(self::EXPECTED[$role->value], $keys);

        // Every visible item opens (redirects followed) with 200.
        foreach ($nav as $item) {
            $this->actingAs($user)->followingRedirects()->get($item['href'])
                ->assertOk();
        }

        // Every parameterless registry item that is hidden is refused (403/404).
        foreach ((new NavigationBuilder)->definitions() as $def) {
            if (in_array($def['key'], $keys, true) || isset($def['params']) || ! \Route::has($def['route'])) {
                continue;
            }

            $status = $this->actingAs($user)->get(route($def['route']))->status();
            $this->assertContains($status, [403, 404], "{$role->value} should not open {$def['key']} (got {$status})");
        }
    }

    public function test_active_flag_follows_current_route(): void
    {
        $nav = collect($this->navigation($this->makeUser(UserRole::BeaconAdmin)))->keyBy('key');

        $this->assertTrue($nav['account']['active']);
        $this->assertFalse($nav['admin']['active']);
    }

    public function test_two_factor_item_only_for_eligible_roles_when_mfa_enabled(): void
    {
        config(['mfa.enabled' => true]);

        foreach (UserRole::cases() as $role) {
            $user = $this->makeUser($role);
            $nav = collect($this->navigation($user))->keyBy('key');
            $eligible = in_array($role->value, (array) config('mfa.roles'), true);

            $this->assertSame($eligible, $nav->has('account.two-factor'), $role->value);
            $this->actingAs($user)->get(route('two-factor.show'))->assertStatus($eligible ? 200 : 403);
        }
    }

    public function test_sme_without_enterprise_has_no_enterprise_items(): void
    {
        $user = User::factory()->create(['role' => UserRole::SmeUser]);
        $user->forceFill(['is_active' => true])->save();

        $keys = array_column($this->navigation($user->fresh()), 'key');

        foreach (['enterprises', 'transactions', 'documents', 'reports', 'readiness', 'compliance', 'referrals'] as $key) {
            $this->assertNotContains($key, $keys);
        }
    }

    public function test_guests_get_no_navigation(): void
    {
        $page = $this->get('/login')->assertOk()->viewData('page');

        $this->assertNull($page['props']['auth']['user']);
        $this->assertSame([], $page['props']['auth']['navigation']);
    }
}
