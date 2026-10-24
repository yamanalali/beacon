<?php

namespace Tests\Feature\Offline;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\ReadinessAssessment;
use App\Models\User;
use App\Support\OfflinePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase, \Tests\Feature\Readiness\ReadinessTestHelpers;

    private function business(): array
    {
        $user = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise = Enterprise::query()->create(['business_name' => 'Offline store', 'location' => 'damascus']);
        $enterprise->members()->attach($user);

        return [$user, $enterprise];
    }

    private function headers(User $user, string $key): array
    {
        return ['X-Beacon-Operation-Key' => $key, 'X-Beacon-Offline-Scope' => OfflinePolicy::scope($user), 'X-Beacon-Offline-Replay' => '1'];
    }

    private function payload(): array
    {
        return ['type' => 'sale', 'transaction_date' => now()->toDateString(), 'amount' => '100', 'currency' => 'SYP', 'category' => 'general_sales', 'payment_status' => 'unpaid'];
    }

    public function test_replayed_transaction_is_created_exactly_once_and_changed_payload_is_rejected(): void
    {
        [$user, $enterprise] = $this->business();
        $key = (string) Str::uuid();
        $url = route('enterprises.transactions.store', $enterprise);
        $this->actingAs($user)->postJson($url, $this->payload(), $this->headers($user, $key))->assertOk()->assertJson(['synced' => true, 'operation_key' => $key]);
        $this->postJson($url, $this->payload(), $this->headers($user, $key))->assertOk()->assertJson(['synced' => true]);
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseCount('offline_operations', 1);
        $this->postJson($url, [...$this->payload(), 'amount' => '200'], $this->headers($user, $key))->assertStatus(409);
    }

    public function test_online_response_loss_can_be_replayed_without_a_second_payment(): void
    {
        [$user, $enterprise] = $this->business();
        $url = route('enterprises.transactions.store', $enterprise);
        $key = (string) Str::uuid();
        $headers = $this->headers($user, $key);
        unset($headers['X-Beacon-Offline-Replay']);
        $this->actingAs($user)->post($url, $this->payload(), $headers)->assertRedirect();
        $transaction = FinancialTransaction::query()->sole();
        $page = $this->get(route('transactions.show', $transaction));
        $versions = $page->headers->get('X-Beacon-Offline-Versions');
        $payKey = (string) Str::uuid();
        $payHeaders = [...$this->headers($user, $payKey), 'X-Beacon-Offline-Versions' => $versions];
        $payment = ['amount' => '20', 'settlement_date' => now()->toDateString()];
        $this->postJson(route('transactions.settlements.store', $transaction), $payment, $payHeaders)->assertOk();
        $this->postJson(route('transactions.settlements.store', $transaction), $payment, $payHeaders)->assertOk();
        $this->assertSame(1, $transaction->settlements()->count());
    }

    public function test_stale_or_missing_versions_cannot_overwrite_a_changed_record(): void
    {
        [$user, $enterprise] = $this->business();
        $this->actingAs($user)->post(route('enterprises.transactions.store', $enterprise), $this->payload())->assertRedirect();
        $transaction = FinancialTransaction::query()->sole();
        $versions = $this->get(route('transactions.show', $transaction))->headers->get('X-Beacon-Offline-Versions');
        $transaction->forceFill(['counterparty' => 'Changed elsewhere'])->save();
        $this->postJson(route('transactions.settlements.store', $transaction), ['amount' => '20', 'settlement_date' => now()->toDateString()], [...$this->headers($user, (string) Str::uuid()), 'X-Beacon-Offline-Versions' => $versions])->assertStatus(409);
        $this->assertDatabaseCount('transaction_settlements', 0);
        $this->assertDatabaseCount('offline_operations', 0);
    }

    public function test_validation_and_tenant_policies_still_apply_and_failed_operations_can_be_corrected(): void
    {
        [$user, $enterprise] = $this->business();
        [$other] = $this->business();
        $key = (string) Str::uuid();
        $url = route('enterprises.transactions.store', $enterprise);
        $this->actingAs($user)->postJson($url, [...$this->payload(), 'amount' => 'bad'], $this->headers($user, $key))->assertStatus(422);
        $this->assertDatabaseCount('offline_operations', 0);
        $this->actingAs($other)->postJson($url, $this->payload(), $this->headers($other, $key))->assertForbidden();
        $this->actingAs($user)->postJson($url, $this->payload(), $this->headers($user, $key))->assertOk();
        $this->postJson($url, $this->payload(), $this->headers($other, (string) Str::uuid()))->assertForbidden();
    }

    public function test_readiness_page_supplies_child_versions_and_multiple_answers_sync_in_order(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $this->publishedRuleSet([['code' => 'one', 'weight' => 1], ['code' => 'two', 'weight' => 1]]);
        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise))->assertRedirect();
        $assessment = ReadinessAssessment::query()->sole();
        $page = $this->get(route('readiness-assessments.show', $assessment))->viewData('page');
        $versions = $page['props']['offline_versions'];
        $results = $assessment->results()->get();
        foreach ($results as $result) {
            $this->assertArrayHasKey($result->getMorphClass().':'.$result->id, $versions);
            $response = $this->putJson(route('readiness-assessments.results.update', [$assessment, $result]), [
                'outcome' => 'met', 'note' => 'Offline answer', '_beacon_versions' => json_encode($versions),
            ], $this->headers($advisor, (string) Str::uuid()))->assertOk()->assertJson(['synced' => true]);
            $versions = array_replace($versions, $response->json('versions'));
        }
        $this->assertSame(2, $assessment->results()->where('outcome', 'met')->count());
    }

    public function test_report_snapshot_is_authorized_and_never_includes_another_enterprises_data(): void
    {
        [$user, $enterprise] = $this->business();
        [$other] = $this->business();
        $this->actingAs($user)->post(route('enterprises.transactions.store', $enterprise), $this->payload())->assertRedirect();
        $this->getJson(route('offline.report-data', $enterprise))->assertOk()->assertJsonPath('complete', true)->assertJsonCount(1, 'transactions')->assertJsonPath('transactions.0.amount', '100.00');
        $this->actingAs($other)->getJson(route('offline.report-data', $enterprise))->assertForbidden();
    }

    public function test_all_offline_routes_exist_and_asset_manifest_contains_the_offline_shell(): void
    {
        foreach (OfflinePolicy::WRITES as $route) {
            $this->assertTrue(Route::has($route), $route);
        }
        $this->getJson('/offline/assets')->assertOk()->assertJsonFragment(['/offline.html']);
    }

    public function test_offline_session_is_private_and_account_actions_cannot_be_replayed(): void
    {
        [$user] = $this->business();
        $this->getJson('/offline/session')->assertUnauthorized();
        $this->actingAs($user)->getJson('/offline/session')->assertOk()->assertJsonStructure(['scope', 'csrf'])->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/logout', [], $this->headers($user, (string) Str::uuid()))->assertStatus(422);
        $this->assertAuthenticatedAs($user);
    }
}
