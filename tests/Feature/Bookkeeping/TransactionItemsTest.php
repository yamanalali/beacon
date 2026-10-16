<?php

namespace Tests\Feature\Bookkeeping;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\ReceiptExtraction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionItemsTest extends TestCase
{
    use RefreshDatabase;

    private Enterprise $enterprise;
    private User $sme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $this->sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->enterprise->members()->attach($this->sme->id);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'purchase',
            'transaction_date' => '2026-10-01',
            'amount' => '25000',
            'currency' => 'SYP',
            'category' => array_key_first(config('bookkeeping.categories.purchase')),
            'payment_status' => 'paid',
        ], $overrides);
    }

    private function store(array $payload)
    {
        return $this->actingAs($this->sme)->post(route('enterprises.transactions.store', $this->enterprise), $payload);
    }

    public function test_creates_transaction_with_items_and_computes_missing_total(): void
    {
        $this->store($this->payload(['items' => [
            ['description' => 'بيتزا', 'quantity' => 2, 'unit_price' => '7500'],
            ['description' => 'مشروب', 'quantity' => '1', 'unit_price' => 10000, 'total' => 10000],
        ]]))->assertRedirect()->assertSessionMissing('warning');

        $t = FinancialTransaction::query()->sole();
        $items = $t->items;
        $this->assertCount(2, $items);
        $this->assertSame('بيتزا', $items[0]->description);
        $this->assertSame('15000.00', (string) $items[0]->total);
        $this->assertSame(0, $items[0]->position);
        $this->assertSame(1, $items[1]->position);
        $this->assertFalse($t->itemsMismatch());
    }

    public function test_computed_total_rounds_half_up(): void
    {
        $this->store($this->payload(['amount' => '3.70', 'items' => [
            ['description' => 'x', 'quantity' => '1.5', 'unit_price' => '2.465'],
        ]]))->assertRedirect();

        // unit_price stored at 2 decimals (2.47); total = 1.5 * 2.47 = 3.705 -> 3.71
        $item = TransactionItem::query()->sole();
        $this->assertSame('2.47', (string) $item->unit_price);
        $this->assertSame('3.71', (string) $item->total);
    }

    public function test_creates_transaction_without_items(): void
    {
        $this->store($this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $this->store($this->payload(['items' => []]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, FinancialTransaction::query()->count());
        $this->assertSame(0, TransactionItem::query()->count());
    }

    public function test_item_validation_errors(): void
    {
        $this->store($this->payload(['items' => [
            ['description' => '', 'quantity' => 0, 'unit_price' => -1, 'total' => -5],
            ['description' => str_repeat('a', 256), 'quantity' => 'abc', 'unit_price' => 'x'],
            ['quantity' => -2],
        ]]))->assertSessionHasErrors([
            'items.0.description', 'items.0.quantity', 'items.0.unit_price', 'items.0.total',
            'items.1.description', 'items.1.quantity', 'items.1.unit_price',
            'items.2.description', 'items.2.quantity', 'items.2.unit_price',
        ]);

        $this->store($this->payload(['items' => 'nope']))->assertSessionHasErrors('items');

        $this->assertSame(0, FinancialTransaction::query()->count());
        $this->assertSame(0, TransactionItem::query()->count());
    }

    public function test_mismatch_only_warns_and_still_saves(): void
    {
        $this->store($this->payload(['amount' => '11000', 'items' => [
            ['description' => 'بند', 'quantity' => 1, 'unit_price' => 10000],
        ]]))->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('warning');

        $t = FinancialTransaction::query()->sole();
        $this->assertSame('11000.00', (string) $t->amount);
        $this->assertTrue($t->itemsMismatch());

        $this->actingAs($this->sme)->get(route('transactions.show', $t))
            ->assertInertia(fn (Assert $page) => $page
                ->where('transaction.items_total', 10000)
                ->where('transaction.items_mismatch', true));
    }

    public function test_show_and_correction_props_shape_uses_numbers(): void
    {
        $this->store($this->payload(['amount' => '15000.50', 'items' => [
            ['description' => 'بيتزا', 'quantity' => '2.5', 'unit_price' => '6000.2'],
        ]]));
        $t = FinancialTransaction::query()->sole();
        $id = $t->items->first()->id;

        $check = fn (string $key) => fn (Assert $page) => $page
            ->has($key.'.items', 1)
            ->where($key.'.items.0', fn ($item) => collect($item)->all() === [
                'id' => $id, 'description' => 'بيتزا', 'quantity' => 2.5, 'unit_price' => 6000.2, 'total' => 15000.5,
            ])
            ->where($key.'.items_total', 15000.5)
            ->where($key.'.items_mismatch', false);

        $this->actingAs($this->sme)->get(route('transactions.show', $t))->assertInertia($check('transaction'));
        $this->actingAs($this->sme)->get(route('transactions.correct.edit', $t))->assertInertia($check('original'));
    }

    public function test_show_without_items_has_empty_items(): void
    {
        $this->store($this->payload());
        $t = FinancialTransaction::query()->sole();

        $this->actingAs($this->sme)->get(route('transactions.show', $t))
            ->assertInertia(fn (Assert $page) => $page
                ->where('transaction.items', [])
                ->where('transaction.items_total', null)
                ->where('transaction.items_mismatch', false));
    }

    public function test_correction_replaces_item_set(): void
    {
        $this->store($this->payload(['amount' => '300', 'items' => [
            ['description' => 'a', 'quantity' => 1, 'unit_price' => 100],
            ['description' => 'b', 'quantity' => 2, 'unit_price' => 100],
        ]]));
        $original = FinancialTransaction::query()->sole();

        $this->actingAs($this->sme)->post(route('transactions.correct', $original), $this->payload([
            'amount' => '500',
            'correction_reason' => 'تعديل البنود',
            'items' => [['description' => 'c', 'quantity' => 5, 'unit_price' => 100]],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $corrected = FinancialTransaction::query()->where('corrects_transaction_id', $original->id)->sole();
        $this->assertSame(['c'], $corrected->items->pluck('description')->all());
        $this->assertSame('500.00', (string) $corrected->items->first()->total);
        // History: the superseded original keeps its own items.
        $this->assertSame(['a', 'b'], $original->fresh()->items->pluck('description')->all());

        // Correction with items: [] clears the set on the new version.
        $this->actingAs($this->sme)->post(route('transactions.correct', $corrected), $this->payload([
            'amount' => '500', 'correction_reason' => 'بلا بنود', 'items' => [],
        ]))->assertSessionHasNoErrors();
        $latest = FinancialTransaction::query()->where('corrects_transaction_id', $corrected->id)->sole();
        $this->assertCount(0, $latest->items);
    }

    public function test_correction_without_items_key_keeps_previous_items(): void
    {
        $this->store($this->payload(['amount' => '300', 'items' => [
            ['description' => 'a', 'quantity' => '1.5', 'unit_price' => 100, 'total' => 150],
            ['description' => 'b', 'quantity' => 1, 'unit_price' => 150],
        ]]));
        $original = FinancialTransaction::query()->sole();

        $this->actingAs($this->sme)->post(route('transactions.correct', $original), $this->payload([
            'amount' => '300', 'correction_reason' => 'تعديل التاريخ', 'transaction_date' => '2026-10-02',
        ]))->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('warning');

        $corrected = FinancialTransaction::query()->where('corrects_transaction_id', $original->id)->sole();
        $this->assertSame(['a', 'b'], $corrected->items->pluck('description')->all());
        $this->assertSame(['1.500', '1.000'], $corrected->items->map(fn ($i) => (string) $i->quantity)->all());
        $this->assertSame(['150.00', '150.00'], $corrected->items->map(fn ($i) => (string) $i->total)->all());
        $this->assertSame(['a', 'b'], $original->fresh()->items->pluck('description')->all());
    }

    public function test_manual_store_without_items_key_has_no_items(): void
    {
        $this->store($this->payload())->assertSessionHasNoErrors()->assertSessionMissing('warning');
        $this->assertCount(0, FinancialTransaction::query()->sole()->items);
    }

    private function mismatch(string $currency, string $amount, array $totals): bool
    {
        $t = new FinancialTransaction;
        $t->setRawAttributes(['amount' => $amount, 'currency' => $currency]);
        $t->setRelation('items', collect(array_map(function (string $total): TransactionItem {
            $i = new TransactionItem;
            $i->setRawAttributes(['total' => $total]);
            return $i;
        }, $totals)));

        return $t->itemsMismatch();
    }

    public function test_mismatch_tolerance_is_half_smallest_unit(): void
    {
        // SYP: |diff| <= 0.5
        $this->assertFalse($this->mismatch('SYP', '100000.00', ['33333.33', '33333.33', '33333.33'])); // 0.01
        $this->assertFalse($this->mismatch('SYP', '1000.00', ['1000.40']));
        $this->assertFalse($this->mismatch('SYP', '1000.00', ['1000.50']));
        $this->assertTrue($this->mismatch('SYP', '1000.00', ['1000.60']));
        $this->assertTrue($this->mismatch('SYP', '1000.00', ['999.40']));
        // Others: |diff| <= 0.005
        $this->assertFalse($this->mismatch('USD', '10.000', ['10.004']));
        $this->assertTrue($this->mismatch('USD', '10.00', ['10.01']));
        $this->assertTrue($this->mismatch('USD', '10.00', ['9.99']));
        $this->assertFalse($this->mismatch('USD', '10.00', ['4.00', '6.00']));
    }

    public function test_syp_rounding_difference_does_not_warn_end_to_end(): void
    {
        $this->store($this->payload(['amount' => '100000', 'items' => [
            ['description' => 'x', 'quantity' => 3, 'unit_price' => '33333.33'],
        ]]))->assertRedirect()->assertSessionMissing('warning');

        $this->store($this->payload(['amount' => '10', 'currency' => 'USD', 'items' => [
            ['description' => 'x', 'quantity' => 1, 'unit_price' => '10.01'],
        ]]))->assertRedirect()->assertSessionHas('warning');
    }

    public function test_items_are_deleted_with_transaction(): void
    {
        $this->store($this->payload(['items' => [['description' => 'a', 'quantity' => 1, 'unit_price' => 25000]]]));
        $this->assertSame(1, TransactionItem::query()->count());

        FinancialTransaction::query()->sole()->settlements()->delete();
        FinancialTransaction::query()->sole()->delete();

        $this->assertSame(0, TransactionItem::query()->count());
    }

    public function test_receipt_review_confirm_accepts_items(): void
    {
        $doc = new Document;
        $doc->forceFill(['enterprise_id' => $this->enterprise->id, 'title' => 'r', 'original_name' => 'r.jpg', 'disk' => 'local', 'path' => 'x', 'mime_type' => 'image/jpeg', 'size' => 1])->save();
        $x = new ReceiptExtraction;
        $x->forceFill(['enterprise_id' => $this->enterprise->id, 'document_id' => $doc->id, 'status' => 'pending_review', 'extractor' => 'fake'])->save();

        $this->actingAs($this->sme)->post(route('extractions.confirm', $x), $this->payload(['amount' => '16000', 'items' => [
            ['description' => 'بيتزا', 'quantity' => 1, 'unit_price' => 15000, 'total' => 15000],
        ]]))->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('warning');

        $t = FinancialTransaction::query()->sole();
        $this->assertSame(['بيتزا'], $t->items->pluck('description')->all());
    }
}
