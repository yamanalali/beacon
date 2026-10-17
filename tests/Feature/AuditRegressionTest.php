<?php

namespace Tests\Feature;

use App\Actions\Bookkeeping\ConfirmReceiptExtractionAction;
use App\Actions\Bookkeeping\CorrectTransactionAction;
use App\Actions\Bookkeeping\CreateTransactionAction;
use App\Actions\Bookkeeping\RecordSettlementAction;
use App\Actions\Bookkeeping\StartReceiptExtractionAction;
use App\Contracts\ReceiptExtractor;
use App\Enums\UserRole;
use App\Models\ComplianceRequirement;
use App\Models\ComplianceRequirementVersion;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\EnterpriseComplianceRecord;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Services\Ai\ReceiptExtractionResult;
use App\Services\FinancialReports\FinancialReportService;
use App\Support\TwoFactor\TwoFactorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Page;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function setupSme(): array
    {
        $e = Enterprise::query()->create(['business_name' => 'Audit enterprise', 'location' => 'damascus']);
        $u = User::factory()->create(['role' => UserRole::SmeUser]);
        $e->members()->attach($u->id);

        return [$e, $u];
    }

    private function data(array $extra = []): array
    {
        return array_merge(['type' => 'sale', 'transaction_date' => '2026-01-01', 'amount' => '100', 'currency' => 'USD', 'category' => 'general_sales', 'payment_status' => 'unpaid'], $extra);
    }

    public function test_correction_should_preserve_actual_payment_dates(): void
    {
        [$e,$u] = $this->setupSme();
        $t = app(CreateTransactionAction::class)->execute($u, $e, $this->data());
        app(RecordSettlementAction::class)->execute($u, $t, ['settlement_date' => '2026-02-01', 'amount' => '100']);
        $from = CarbonImmutable::parse('2026-02-01');
        $to = CarbonImmutable::parse('2026-02-28');
        $service = app(FinancialReportService::class);
        $before = $service->generate('cash_flow', $e, $from, $to)->toArray();
        app(CorrectTransactionAction::class)->execute($u, $t->fresh(), $this->data(['payment_status' => 'paid', 'description' => 'Only correcting description', 'correction_reason' => 'Description correction']));
        $after = $service->generate('cash_flow', $e, $from, $to)->toArray();
        $this->assertSame($before['sections'], $after['sections'], 'Cash actually collected in February must stay in February after editing only the description.');
    }

    public function test_two_extractions_of_one_document_should_not_make_duplicate_transactions(): void
    {
        [$e,$u] = $this->setupSme();
        $d = new Document;
        $d->forceFill(['enterprise_id' => $e->id, 'title' => 'Audit receipt', 'original_name' => 'receipt.jpg', 'disk' => 'local', 'path' => 'audit.jpg', 'mime_type' => 'image/jpeg', 'size' => 100, 'uploaded_by' => $u->id])->save();
        $start = app(StartReceiptExtractionAction::class);
        $x = $start->execute($u, $d);
        $y = $start->execute($u, $d);
        app(ConfirmReceiptExtractionAction::class)->confirm($u, $x, $this->data(['payment_status' => 'paid']));
        try {
            app(ConfirmReceiptExtractionAction::class)->confirm($u, $y, $this->data(['payment_status' => 'paid']));
        } catch (HttpException $exception) {
        }
        $this->assertSame(1, FinancialTransaction::query()->count(), 'Two reads of the same document created two posted financial transactions.');
    }

    public function test_recovery_code_must_be_consumed_atomically_between_requests(): void
    {
        config(['mfa.enabled' => true]);
        $user = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $service = app(TwoFactorService::class);
        $codes = $service->regenerateRecoveryCodes($user);
        $requestA = $user->fresh();
        $requestB = $user->fresh();
        $this->assertTrue($service->useRecoveryCode($requestA, $codes[0]));
        $this->assertFalse($service->useRecoveryCode($requestB, $codes[0]), 'A second request holding the same initially loaded user can consume a one-time recovery code again.');
    }

    public function test_new_compliance_version_must_not_inherit_old_completed_status(): void
    {
        [$e,$sme] = $this->setupSme();
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $r = ComplianceRequirement::query()->create(['code' => 'AUDIT-REQ', 'category' => 'licensing']);
        $v1 = $r->versions()->create(['version_number' => 1, 'title_ar' => 'Original requirement', 'status' => ComplianceRequirementVersion::STATUS_PUBLISHED]);
        EnterpriseComplianceRecord::query()->create(['enterprise_id' => $e->id, 'compliance_requirement_id' => $r->id, 'compliance_requirement_version_id' => $v1->id, 'status' => 'completed']);
        $r->versions()->create(['version_number' => 2, 'title_ar' => 'New requirement needing a new document', 'requires_document' => true, 'status' => ComplianceRequirementVersion::STATUS_PUBLISHED]);
        $this->actingAs($admin)->get(route('enterprises.compliance.index', $e))->assertInertia(fn (Page $p) => $p->where('items.0.version.version_number', 2)->where('items.0.status', 'not_started')->where('items.0.needs_version_review', true)->where('items.0.missing_document', true));
        $record = EnterpriseComplianceRecord::query()->sole();
        $this->assertSame('completed', $record->status);
        $this->assertSame($v1->id, $record->compliance_requirement_version_id);
        $this->assertFalse($record->isCompleted());
        $this->assertSame(0, EnterpriseComplianceRecord::query()->forCurrentVersion()->count());
        $this->assertSame(1, EnterpriseComplianceRecord::query()->needingVersionReview()->count());
        $this->actingAs($sme)->get(route('dashboard.sme'))->assertInertia(fn (Page $p) => $p->where('alerts.0.key', 'compliance_version_review'));
    }

    public function test_correction_keeps_payment_notes_author_and_dates_across_multiple_versions(): void
    {
        [$e,$u] = $this->setupSme();
        $t = app(CreateTransactionAction::class)->execute($u, $e, $this->data(['items' => [['description' => 'Original item', 'quantity' => 2, 'unit_price' => 50]]]));
        $payment = app(RecordSettlementAction::class)->execute($u, $t, ['settlement_date' => '2026-02-01', 'amount' => '40', 'note' => 'Bank transfer']);
        $correct = app(CorrectTransactionAction::class);
        $second = $correct->execute($u, $t->fresh(), $this->data(['amount' => '120', 'correction_reason' => 'Correct total']));
        $third = $correct->execute($u, $second, $this->data(['amount' => '140', 'correction_reason' => 'Correct total again']));
        $this->assertSame('partially_paid', $third->payment_status->value);
        $this->assertSame(4000, $third->settledMinor());
        $copy = $third->settlements->sole();
        $this->assertSame('2026-02-01', $copy->settlement_date->toDateString());
        $this->assertSame($payment->note, $copy->note);
        $this->assertSame($payment->created_by, $copy->created_by);
        $this->assertSame(1, $t->settlements()->count());
        $this->assertSame(1, $second->settlements()->count());
        $this->assertSame(1, FinancialTransaction::query()->posted()->count());
        foreach ([$t, $second, $third] as $version) {
            $this->assertSame('Original item', $version->items()->sole()->description);
            $this->assertSame('100.00', (string) $version->items()->sole()->total);
        }
    }

    public function test_correction_of_partial_payment_needs_no_new_paid_amount(): void
    {
        [$e,$u] = $this->setupSme();
        $t = app(CreateTransactionAction::class)->execute($u, $e, $this->data(['payment_status' => 'partially_paid', 'paid_amount' => '40']));
        $this->actingAs($u)->post(route('transactions.correct', $t), $this->data(['payment_status' => 'partially_paid', 'amount' => '120', 'correction_reason' => 'Correct invoice total']))->assertSessionHasNoErrors()->assertRedirect();
        $new = FinancialTransaction::query()->posted()->sole();
        $this->assertSame(4000, $new->settledMinor());
        $this->assertSame(8000, $new->outstandingMinor());
    }

    public function test_correction_cannot_reduce_below_paid_total_or_change_settled_currency_and_type(): void
    {
        [$e,$u] = $this->setupSme();
        $t = app(CreateTransactionAction::class)->execute($u, $e, $this->data(['payment_status' => 'paid']));
        foreach ([['amount' => '90'], ['currency' => 'SYP'], ['type' => 'income', 'category' => 'other_income']] as $overrides) {
            try {
                app(CorrectTransactionAction::class)->execute($u, $t->fresh(), $this->data($overrides + ['correction_reason' => 'Invalid correction']));
                $this->fail('Unsafe correction accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey(array_key_first($overrides), $exception->errors());
            }
            $this->assertSame('posted', $t->fresh()->status->value);
            $this->assertSame(1, FinancialTransaction::query()->count());
        }
    }

    public function test_total_correction_recalculates_status_without_inventing_cash(): void
    {
        [$e,$u] = $this->setupSme();
        $t = app(CreateTransactionAction::class)->execute($u, $e, $this->data(['payment_status' => 'paid']));
        $new = app(CorrectTransactionAction::class)->execute($u, $t, $this->data(['amount' => '120', 'payment_status' => 'paid', 'correction_reason' => 'Correct total']));
        $this->assertSame('partially_paid', $new->payment_status->value);
        $this->assertSame(10000, $new->settledMinor());
        app(RecordSettlementAction::class)->execute($u, $new, ['settlement_date' => '2026-03-01', 'amount' => '20']);
        $this->assertSame('paid', $new->fresh()->payment_status->value);
    }

    public function test_totp_replay_is_rejected_even_with_a_stale_loaded_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $google = app(Google2FA::class);
        $secret = $google->generateSecretKey(32);
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $a = $user->fresh();
        $b = $user->fresh();
        $code = $google->getCurrentOtp($secret);
        $service = app(TwoFactorService::class);
        $this->assertTrue($service->verifyCode($a, $code));
        $this->assertFalse($service->verifyCode($b, $code));
    }

    public function test_extraction_reuses_pending_review_and_refuses_already_linked_document(): void
    {
        [$e,$u] = $this->setupSme();
        $d = new Document;
        $d->forceFill(['enterprise_id' => $e->id, 'title' => 'Receipt', 'original_name' => 'r.jpg', 'disk' => 'local', 'path' => 'r.jpg', 'mime_type' => 'image/jpeg', 'size' => 10, 'uploaded_by' => $u->id])->save();
        $start = app(StartReceiptExtractionAction::class);
        $a = $start->execute($u, $d);
        $b = $start->execute($u, $d);
        $this->assertSame($a->id, $b->id);
        // Reproduce legacy duplicates that may already exist before this fix.
        $legacy = $a->replicate(['public_id']);
        $legacy->save();
        app(ConfirmReceiptExtractionAction::class)->confirm($u, $a, $this->data());
        $this->assertSame('rejected', $legacy->fresh()->status->value);
        try {
            app(ConfirmReceiptExtractionAction::class)->reject($u, $a);
            $this->fail('A stale rejection changed a confirmed extraction.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame('confirmed', $a->fresh()->status->value);

        $this->actingAs($u)->post(route('extractions.confirm', $legacy), $this->data())->assertStatus(409);
        $this->actingAs($u)->post(route('documents.extractions.store', $d))->assertStatus(409);
        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_deduplicated_receipt_review_keeps_new_invoice_fields(): void
    {
        [$enterprise, $sme] = $this->setupSme();
        $document = new Document;
        $document->forceFill(['enterprise_id' => $enterprise->id, 'title' => 'Receipt', 'original_name' => 'r.jpg', 'disk' => 'local', 'path' => 'r.jpg', 'mime_type' => 'image/jpeg', 'size' => 10, 'uploaded_by' => $sme->id])->save();
        $this->app->bind(ReceiptExtractor::class, fn () => new class implements ReceiptExtractor
        {
            public function name(): string
            {
                return 'test';
            }

            public function extract(Document $document): ReceiptExtractionResult
            {
                return new ReceiptExtractionResult(
                    transactionDate: '2026-01-01', amount: '100', currency: 'USD', counterparty: 'Store',
                    rawText: 'فاتورة مشتريات مطعم بيتزا',
                    items: [['description' => 'Goods', 'quantity' => 2, 'unit_price' => 50, 'total' => 100]],
                    invoiceNumber: 'INV-123',
                );
            }
        });
        $action = app(StartReceiptExtractionAction::class);
        $review = $action->execute($sme, $document);
        $again = $action->execute($sme, $document);
        $this->assertSame($review->id, $again->id);
        $this->assertSame('INV-123', $review->suggested_invoice_number);
        $this->assertSame('Goods', $review->suggested_items[0]['description']);
        $this->assertNotNull($review->suggested_category);
        $this->assertDatabaseCount('receipt_extractions', 1);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_required_frontend_pages_exist(): void
    {
        foreach (['Ai/Assistant', 'Ai/Escalations/Index', 'Ai/Escalations/Show', 'Ai/Knowledge/Published', 'Ai/Knowledge/Index', 'Notifications/Index'] as $page) {
            $this->assertFileExists(resource_path('js/Pages/'.$page.'.tsx'));
        }
    }
}
