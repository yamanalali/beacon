<?php

namespace Tests\Feature\Bookkeeping;

use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionFormExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function business(): array
    {
        $enterprise = Enterprise::query()->create(['business_name' => 'Store', 'location' => 'damascus']);
        $user = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->members()->attach($user);

        return [$enterprise, $user];
    }

    private function record(Enterprise $enterprise, User $user, array $overrides = []): FinancialTransaction
    {
        $this->actingAs($user)->post(route('enterprises.transactions.store', $enterprise), array_replace([
            'type' => 'sale', 'transaction_date' => now()->toDateString(), 'amount' => '100', 'currency' => 'SYP',
            'category' => 'general_sales', 'payment_status' => 'paid', 'counterparty' => 'Customer A',
        ], $overrides))->assertSessionHasNoErrors();

        return FinancialTransaction::query()->latest('id')->firstOrFail();
    }

    public function test_localized_amounts_and_item_numbers_are_saved_as_exact_decimals(): void
    {
        [$enterprise, $user] = $this->business();
        $transaction = $this->record($enterprise, $user, ['amount' => '١٬٢٣٤٫٥٠', 'payment_status' => 'partially_paid', 'paid_amount' => '۲۰۰٫۲۵', 'items' => [
            ['description' => 'Item', 'quantity' => '٢٫٥', 'unit_price' => '١٠٫٢٠', 'total' => '٢٥٫٥٠'],
        ]]);
        $this->assertSame('1234.50', $transaction->amount);
        $this->assertSame('200.25', $transaction->settlements()->sole()->amount);
        $this->assertSame('25.50', $transaction->items()->sole()->total);
        $this->assertSame('2.500', $transaction->items()->sole()->quantity);
    }

    public function test_ambiguous_grouping_and_extra_decimals_are_rejected_instead_of_guessed(): void
    {
        [$enterprise, $user] = $this->business();
        foreach (['1,5', '12,34', '1,234.567'] as $amount) {
            $this->actingAs($user)->post(route('enterprises.transactions.store', $enterprise), ['type' => 'sale', 'transaction_date' => now()->toDateString(), 'amount' => $amount, 'currency' => 'SYP', 'category' => 'general_sales', 'payment_status' => 'paid'])->assertSessionHasErrors('amount');
        }
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->record($enterprise, $user, ['amount' => '1,234.50']);
        $this->assertSame('1234.50', FinancialTransaction::query()->sole()->amount);
    }

    public function test_create_form_suggests_names_only_from_its_own_enterprise_and_exposes_review_requirement(): void
    {
        [$enterprise, $user] = $this->business();
        [$other, $outsider] = $this->business();
        $this->record($enterprise, $user);
        $this->record($enterprise, $user);
        $this->record($other, $outsider, ['counterparty' => 'Private outsider']);
        $user->forceFill(['access_permissions' => ['require_approval' => true]])->save();
        $this->actingAs($user->fresh())->get(route('enterprises.transactions.create', $enterprise))->assertInertia(fn (Assert $page) => $page
            ->component('Transactions/Form')->where('counterparties', ['Customer A'])->where('requires_approval', true)->where('template', null));
        $this->get(route('enterprises.transactions.create', $other))->assertForbidden();
    }

    public function test_copy_source_is_scoped_to_enterprise_and_copies_details_without_creating_records(): void
    {
        [$enterprise, $user] = $this->business();
        [$other, $outsider] = $this->business();
        $source = $this->record($enterprise, $user, ['items' => [['description' => 'Invoice line', 'quantity' => '2', 'unit_price' => '50']]]);
        $foreign = $this->record($other, $outsider);
        $this->actingAs($user)->get(route('enterprises.transactions.create', [$enterprise, 'copy_from' => $source->public_id]))->assertInertia(fn (Assert $page) => $page
            ->where('original', null)->where('template.public_id', $source->public_id)->where('template.amount', '100.00')->where('template.items.0.description', 'Invoice line'));
        $this->get(route('enterprises.transactions.create', [$enterprise, 'copy_from' => $foreign->public_id]))->assertNotFound();
        $this->assertDatabaseCount('financial_transactions', 2);
        $this->assertDatabaseCount('transaction_settlements', 2);
    }

    public function test_copying_a_disabled_currency_leaves_only_enabled_choices_and_record_requires_explicit_review(): void
    {
        [$enterprise, $user] = $this->business();
        $source = $this->record($enterprise, $user, ['currency' => 'USD']);
        Currency::findOrFail('USD')->update(['enabled' => false]);
        $this->actingAs($user)->get(route('enterprises.transactions.create', [$enterprise, 'copy_from' => $source->public_id]))->assertInertia(fn (Assert $page) => $page
            ->where('template.currency', 'USD')->where('options.currencies', fn ($currencies) => ! collect($currencies)->contains('value', 'USD')));
    }
}
