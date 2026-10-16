<?php

namespace Tests\Feature;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** The shared DataTable (server mode) expects {data, links, meta} paginator props. */
class DataTablePaginatorShapeTest extends TestCase
{
    use RefreshDatabase;

    private function assertPaginatorShape(AssertableInertia $page, string $prop, int $count, int $total, int $lastPage): AssertableInertia
    {
        return $page
            ->has("{$prop}.data", $count)
            ->has("{$prop}.links.next")
            ->where("{$prop}.meta.total", $total)
            ->where("{$prop}.meta.last_page", $lastPage)
            ->has("{$prop}.meta.current_page")
            ->has("{$prop}.meta.from")
            ->has("{$prop}.meta.to")
            ->has("{$prop}.meta.per_page");
    }

    public function test_admin_users_index_supplies_meta_and_keeps_search_in_page_links(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin, 'name' => 'Admin']);
        User::factory()->count(20)->create(['name' => 'Zed Person']);

        $this->actingAs($admin)->get('/users?search=Zed&page=2')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatorShape($page->component('Administration/Users/Index'), 'users', 5, 20, 2)
                ->where('users.meta.current_page', 2)
                ->where('users.links.prev', fn ($url) => str_contains((string) $url, 'search=Zed')));
    }

    public function test_audit_logs_index_supplies_meta(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $audit = app(RecordAuditLog::class);
        $audit->record($admin, 'alpha.done', $admin);
        $audit->record($admin, 'beta.done', $admin);

        $this->actingAs($admin)->get('/admin/audit-logs?action=alpha.done')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatorShape($page->component('Administration/AuditLogs/Index'), 'logs', 1, 1, 1));
    }

    public function test_transactions_and_documents_index_supply_meta(): void
    {
        $enterprise = Enterprise::query()->create(['business_name' => 'منشأة اختبار', 'location' => 'damascus']);
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->members()->attach($sme->id);

        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), [
            'type' => 'sale', 'transaction_date' => '2026-10-01', 'amount' => '10', 'currency' => 'SYP',
            'category' => 'general_sales', 'payment_status' => 'paid',
        ]);

        $this->actingAs($sme)->get(route('enterprises.transactions.index', $enterprise))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatorShape($page->component('Transactions/Index'), 'transactions', 1, 1, 1));

        $this->actingAs($sme)->get(route('enterprises.documents.index', $enterprise))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $this->assertPaginatorShape($page->component('Documents/Index'), 'documents', 0, 0, 1));
    }
}
