<?php

namespace Tests\Feature\Administration;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Services\Bookkeeping\TransactionCategoryCatalog;
use Database\Seeders\TransactionCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::BeaconAdmin]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'key' => 'insurance', 'label_ar' => 'تأمين', 'label_en' => 'Insurance',
            'type_scope' => 'expense', 'is_active' => true, 'sort' => 100,
        ], $overrides);
    }

    public function test_catalog_falls_back_to_config_when_table_is_empty(): void
    {
        $this->assertSame(0, TransactionCategory::query()->count());

        $catalog = app(TransactionCategoryCatalog::class);
        $this->assertSame(config('bookkeeping.categories.expense'), $catalog->forType('expense'));
        $this->assertSame('إيجار', $catalog->label('expense', 'rent'));
        $this->assertSame(array_keys(config('bookkeeping.categories')), array_keys($catalog->grouped()));
    }

    public function test_seeder_copies_config_categories_once(): void
    {
        $this->seed(TransactionCategorySeeder::class);
        $this->seed(TransactionCategorySeeder::class);

        $expected = collect(config('bookkeeping.categories'))->flatten()->count();
        $this->assertSame($expected, TransactionCategory::query()->count());
        $rent = TransactionCategory::query()->where('key', 'rent')->sole();
        $this->assertSame('expense', $rent->type_scope);
        $this->assertSame('إيجار', $rent->label_ar);
        $this->assertTrue($rent->is_active);

        $this->assertSame(config('bookkeeping.categories.expense'), app(TransactionCategoryCatalog::class)->forType('expense'));
    }

    public function test_admin_can_create_update_and_deactivate_categories(): void
    {
        $this->actingAs($this->admin())->get(route('admin.categories.index'))
            ->assertOk()->assertInertia(fn ($page) => $page->component('Administration/Categories')->has('types', 4));

        $this->post(route('admin.categories.store'), $this->payload())->assertSessionHasNoErrors()->assertSessionHas('success');

        // First save moved the defaults into the table, so nothing was lost.
        $this->assertSame(10, TransactionCategory::query()->count());
        $category = TransactionCategory::query()->where('key', 'insurance')->sole();
        $this->assertArrayHasKey('insurance', app(TransactionCategoryCatalog::class)->forType('expense'));
        $this->assertArrayHasKey('rent', app(TransactionCategoryCatalog::class)->forType('expense'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'transaction_category.created', 'target_id' => (string) $category->id]);

        $this->put(route('admin.categories.update', $category), [
            'key' => 'hacked', 'label_ar' => 'تأمين صحي', 'label_en' => null, 'type_scope' => 'purchase', 'is_active' => true, 'sort' => 5,
        ])->assertSessionHasNoErrors();
        $category->refresh();
        $this->assertSame('insurance', $category->key); // key never changes
        $this->assertSame('تأمين صحي', $category->label_ar);
        $this->assertNull($category->label_en);
        $this->assertSame('purchase', $category->type_scope);
        $this->assertDatabaseHas('audit_logs', ['action' => 'transaction_category.updated', 'target_id' => (string) $category->id]);

        $this->post(route('admin.categories.deactivate', $category))->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertFalse($category->fresh()->is_active);
        $this->assertArrayNotHasKey('insurance', app(TransactionCategoryCatalog::class)->forType('purchase'));
        $this->assertSame('تأمين صحي', app(TransactionCategoryCatalog::class)->label('purchase', 'insurance'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'transaction_category.deactivated']);

        // Deactivating twice is rejected.
        $this->post(route('admin.categories.deactivate', $category))->assertStatus(409);
    }

    public function test_validation_rejects_bad_or_duplicate_keys_and_scopes(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.categories.store'), $this->payload())->assertSessionHasNoErrors();

        $this->post(route('admin.categories.store'), $this->payload())->assertSessionHasErrors('key');
        $this->post(route('admin.categories.store'), $this->payload(['key' => 'Bad Key!']))->assertSessionHasErrors('key');
        $this->post(route('admin.categories.store'), $this->payload(['key' => 'x2', 'type_scope' => 'nonsense']))->assertSessionHasErrors('type_scope');
        $this->post(route('admin.categories.store'), $this->payload(['key' => 'x3', 'label_ar' => '']))->assertSessionHasErrors('label_ar');
    }

    public function test_cannot_deactivate_the_last_active_category_of_a_type(): void
    {
        $this->actingAs($this->admin());
        $this->seed(TransactionCategorySeeder::class);

        $onlySale = TransactionCategory::query()->where('key', 'general_sales')->sole();
        $this->post(route('admin.categories.deactivate', $onlySale))->assertSessionHasErrors('is_active');
        $this->assertTrue($onlySale->fresh()->is_active);

        $this->put(route('admin.categories.update', $onlySale), [
            'label_ar' => 'مبيعات', 'type_scope' => 'sale', 'is_active' => false, 'sort' => 0,
        ])->assertSessionHasErrors('is_active');
        $this->assertTrue($onlySale->fresh()->is_active);
    }

    public function test_deactivated_category_is_rejected_for_new_transactions_but_history_and_corrections_keep_working(): void
    {
        $admin = $this->admin();
        $enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->members()->attach($sme->id);

        $this->actingAs($admin)->post(route('admin.categories.store'), $this->payload())->assertSessionHasNoErrors();
        $category = TransactionCategory::query()->where('key', 'insurance')->sole();

        $tx = [
            'type' => 'expense', 'transaction_date' => '2026-10-01', 'amount' => '50', 'currency' => 'SYP',
            'category' => 'insurance', 'payment_status' => 'paid',
        ];
        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), $tx)->assertSessionHasNoErrors();
        $saved = FinancialTransaction::query()->sole();

        $this->actingAs($admin)->post(route('admin.categories.deactivate', $category))->assertSessionHasNoErrors();

        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), $tx)->assertSessionHasErrors('category');
        $this->assertDatabaseCount('financial_transactions', 1);

        $this->get(route('transactions.show', $saved))->assertInertia(fn ($page) => $page->where('transaction.category_label', 'تأمين'));
        $this->get(route('enterprises.transactions.create', $enterprise))->assertInertia(fn ($page) => $page
            ->where('options.categories.expense', fn ($items) => collect($items)->pluck('value')->doesntContain('insurance')));

        // The original record can still be corrected with its old category.
        $this->post(route('transactions.correct', $saved), $tx + ['amount' => '60', 'correction_reason' => 'تعديل المبلغ'])
            ->assertSessionHasNoErrors();
        $this->get(route('transactions.correct.edit', FinancialTransaction::query()->where('status', 'posted')->sole()))
            ->assertInertia(fn ($page) => $page
                ->where('options.categories.expense', fn ($items) => collect($items)->pluck('value')->contains('insurance')));
    }

    public function test_only_beacon_admins_can_manage_categories(): void
    {
        $category = TransactionCategory::query()->create([
            'key' => 'insurance', 'label_ar' => 'تأمين', 'type_scope' => 'expense', 'is_active' => true, 'sort' => 1,
        ]);

        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('admin.categories.index'))->assertForbidden();
            $this->post(route('admin.categories.store'), $this->payload(['key' => 'other']))->assertForbidden();
            $this->put(route('admin.categories.update', $category), $this->payload())->assertForbidden();
            $this->post(route('admin.categories.deactivate', $category))->assertForbidden();
        }

        $this->assertSame(1, TransactionCategory::query()->count());
        $this->assertTrue($category->fresh()->is_active);
        $this->assertSame('تأمين', $category->fresh()->label_ar);

        auth()->logout();
        $this->get(route('admin.categories.index'))->assertRedirect();
    }
}
