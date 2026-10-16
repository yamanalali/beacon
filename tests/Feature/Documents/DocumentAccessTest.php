<?php

namespace Tests\Feature\Documents;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function enterpriseWithSme(): array
    {
        $enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'daraa']);
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->members()->attach($sme->id);

        return [$enterprise, $sme];
    }

    private function upload(User $user, Enterprise $enterprise, array $extra = [])
    {
        return $this->actingAs($user)->post(route('enterprises.documents.store', $enterprise), array_merge([
            'file' => UploadedFile::fake()->create('../../evil name.pdf', 100, 'application/pdf'),
            'title' => 'فاتورة',
        ], $extra));
    }

    public function test_sme_uploads_to_private_storage_with_random_name(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();

        $this->upload($sme, $enterprise)->assertRedirect()->assertSessionHasNoErrors();

        $doc = Document::query()->sole();
        Storage::disk('local')->assertExists($doc->path);
        $this->assertStringNotContainsString('evil', $doc->path);
        $this->assertStringStartsWith('enterprises/'.$enterprise->id.'/documents/', $doc->path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.uploaded']);
    }

    public function test_executable_and_unknown_types_are_rejected(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();

        $this->upload($sme, $enterprise, ['file' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php')])
            ->assertSessionHasErrors('file');
        $this->upload($sme, $enterprise, ['file' => UploadedFile::fake()->create('a.exe', 1, 'application/x-msdownload')])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_owner_can_download_and_others_cannot(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $this->upload($sme, $enterprise);
        $doc = Document::query()->sole();

        $this->actingAs($sme)->get(route('documents.download', $doc))->assertOk();
        $this->actingAs($sme)->get(route('documents.preview', $doc))->assertOk();

        $otherSme = User::factory()->create(['role' => UserRole::SmeUser]);
        $mfi = User::factory()->create(['role' => UserRole::MfiUser]);
        $tech = User::factory()->create(['role' => UserRole::TechnicalAdmin]);
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);

        foreach ([$otherSme, $mfi, $tech, $advisor] as $user) {
            $this->actingAs($user)->get(route('documents.download', $doc))->assertForbidden();
            $this->actingAs($user)->get(route('documents.preview', $doc))->assertForbidden();
            $this->actingAs($user)->get(route('enterprises.documents.index', $enterprise))->assertForbidden();
            $this->upload($user, $enterprise)->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $this->upload($sme, $enterprise);
        $doc = Document::query()->sole();
        auth()->logout();

        $this->get(route('documents.download', $doc))->assertRedirect(route('login'));
    }

    public function test_document_can_be_linked_to_own_transaction_only(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), [
            'type' => 'expense', 'transaction_date' => '2026-10-01', 'amount' => '10', 'currency' => 'SYP',
            'category' => 'rent', 'payment_status' => 'paid',
        ]);
        $transaction = \App\Models\FinancialTransaction::query()->sole();

        $this->upload($sme, $enterprise, ['transaction' => $transaction->public_id])->assertSessionHasNoErrors();
        $this->assertSame($transaction->id, Document::query()->sole()->financial_transaction_id);

        [$otherEnterprise, $otherSme] = $this->enterpriseWithSme();
        $this->upload($otherSme, $otherEnterprise, ['transaction' => $transaction->public_id])->assertNotFound();
    }

    public function test_storage_path_is_not_exposed_to_client(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $this->upload($sme, $enterprise);
        $doc = Document::query()->sole();

        $this->actingAs($sme)->get(route('enterprises.documents.index', $enterprise))
            ->assertOk()
            ->assertDontSee($doc->path, false);
    }
}
