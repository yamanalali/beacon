<?php

namespace Tests\Feature\Documents;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentExportAndArchiveAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function enterpriseWithSme(): array
    {
        $enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->members()->attach($sme->id);

        return [$enterprise, $sme];
    }

    private function upload(User $user, Enterprise $enterprise): Document
    {
        $this->actingAs($user)->post(route('enterprises.documents.store', $enterprise), [
            'file' => UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf'),
            'title' => 'فاتورة',
        ])->assertRedirect();

        return Document::query()->latest('id')->firstOrFail();
    }

    public function test_export_false_allows_preview_but_blocks_download(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $sme->forceFill(['access_permissions' => ['export' => false]])->save();
        $doc = $this->upload($sme, $enterprise);

        $this->actingAs($sme)->get(route('documents.preview', $doc))->assertOk();
        $this->actingAs($sme)->get(route('documents.download', $doc))->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'document.downloaded',
            'actor_id' => $sme->id,
        ]);
    }

    public function test_archived_document_remains_viewable_but_not_editable(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $doc = $this->upload($sme, $enterprise);

        $this->actingAs($sme)->post(route('documents.archive', $doc), [
            'reason' => 'نسخة مكررة',
        ])->assertRedirect();

        $doc->refresh();
        $this->assertNotNull($doc->archived_at);
        Storage::disk('local')->assertExists($doc->path);

        $this->actingAs($sme)->get(route('documents.show', $doc))->assertOk();
        $this->actingAs($sme)->get(route('documents.preview', $doc))->assertOk();
        $this->actingAs($sme)->get(route('documents.download', $doc))->assertOk();
        $this->actingAs($sme)->put(route('documents.update', $doc), ['title' => 'x'])->assertForbidden();
        $this->actingAs($sme)->post(route('documents.archive', $doc), ['reason' => 'again'])->assertForbidden();

        $this->actingAs($sme)->get(route('enterprises.documents.index', $enterprise))
            ->assertInertia(fn ($page) => $page->has('documents.data', 0));
    }

    public function test_cannot_relink_document_to_foreign_transaction_on_update(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $doc = $this->upload($sme, $enterprise);

        [$otherEnterprise, $otherSme] = $this->enterpriseWithSme();
        $this->actingAs($otherSme)->post(route('enterprises.transactions.store', $otherEnterprise), [
            'type' => 'expense',
            'transaction_date' => '2026-10-01',
            'amount' => '50',
            'currency' => 'SYP',
            'category' => 'rent',
            'payment_status' => 'paid',
        ])->assertRedirect();
        $foreignTx = FinancialTransaction::query()->latest('id')->firstOrFail();

        $this->actingAs($sme)->put(route('documents.update', $doc), [
            'title' => 'فاتورة',
            'transaction' => $foreignTx->public_id,
        ])->assertNotFound();

        $this->assertNull($doc->fresh()->financial_transaction_id);
    }

    public function test_assigned_advisor_can_preview_but_not_archive(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $enterprise->advisors()->attach($advisor->id);
        $doc = $this->upload($sme, $enterprise);

        $this->actingAs($advisor)->get(route('documents.preview', $doc))->assertOk();
        $this->actingAs($advisor)->get(route('documents.download', $doc))->assertOk();
        $this->actingAs($advisor)->post(route('documents.archive', $doc), [
            'reason' => 'غير مسموح',
        ])->assertForbidden();
        $this->assertNull($doc->fresh()->archived_at);
    }
}
