<?php

namespace Tests\Feature\Documents;

use App\Contracts\ReceiptExtractor;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\ReceiptExtraction;
use App\Models\User;
use App\Services\Ai\ReceiptExtractionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UploadExtractTest extends TestCase
{
    use RefreshDatabase;

    private Enterprise $enterprise;

    private User $sme;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $this->sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->enterprise->members()->attach($this->sme->id);
        $this->app->bind(ReceiptExtractor::class, fn () => new class implements ReceiptExtractor {
            public function name(): string { return 'fake'; }
            public function extract(Document $document): ReceiptExtractionResult
            {
                return new ReceiptExtractionResult('2026-10-01', '250.00', 'SYP', 'مورد', 0.4);
            }
        });
    }

    private function upload(User $user, array $extra = [])
    {
        return $this->actingAs($user)->from(route('enterprises.documents.index', $this->enterprise))
            ->post(route('enterprises.documents.store', ['enterprise' => $this->enterprise] + $extra), [
                'file' => UploadedFile::fake()->create('receipt.jpg', 50, 'image/jpeg'),
            ]);
    }

    public function test_upload_always_flashes_created_document_id(): void
    {
        $response = $this->upload($this->sme);
        $document = Document::query()->sole();

        $response->assertRedirect(route('enterprises.documents.index', $this->enterprise))
            ->assertSessionHas('created_document_id', $document->public_id);
        $this->assertDatabaseCount('receipt_extractions', 0);

        $this->actingAs($this->sme)->withSession(['created_document_id' => $document->public_id])
            ->get(route('enterprises.documents.index', $this->enterprise))
            ->assertInertia(fn (Assert $page) => $page->where('flash.created_document_id', $document->public_id));
    }

    public function test_extract_1_creates_extraction_and_redirects_to_review_without_transaction(): void
    {
        $response = $this->upload($this->sme, ['extract' => 1]);
        $document = Document::query()->sole();
        $extraction = ReceiptExtraction::query()->sole();

        $this->assertSame($document->id, $extraction->document_id);
        $response->assertRedirect(route('extractions.show', $extraction))
            ->assertSessionHas('created_document_id', $document->public_id);
        $this->assertSame('pending_review', $extraction->status->value);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_extract_1_without_transaction_permission_falls_back(): void
    {
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);

        $response = $this->upload($admin, ['extract' => 1]);
        $document = Document::query()->sole();

        $response->assertRedirect(route('enterprises.documents.index', $this->enterprise))
            ->assertSessionHas('created_document_id', $document->public_id)
            ->assertSessionHas('info');
        $this->assertDatabaseCount('receipt_extractions', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_extract_must_be_boolean(): void
    {
        $this->upload($this->sme, ['extract' => 'nope'])->assertSessionHasErrors('extract');
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_sme_gets_quick_record_urls(): void
    {
        config(['ai.voice.enabled' => true]);

        $this->actingAs($this->sme)->get(route('enterprises.documents.index', $this->enterprise))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.record_transaction', true)
                ->where('auth.quick_record.enterprise_public_id', $this->enterprise->public_id)
                ->where('auth.quick_record.upload_url', route('enterprises.documents.store', ['enterprise' => $this->enterprise, 'extract' => 1]))
                ->where('auth.quick_record.voice_url', route('ai.voice-drafts.store', $this->enterprise))
                ->where('auth.quick_record.manual_url', route('enterprises.transactions.create', $this->enterprise)));
    }

    public function test_voice_url_is_null_when_voice_disabled(): void
    {
        config(['ai.voice.enabled' => false]);

        $this->actingAs($this->sme)->get(route('enterprises.documents.index', $this->enterprise))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.record_transaction', true)
                ->where('auth.quick_record.voice_url', null));
    }

    public function test_non_sme_roles_get_no_quick_record(): void
    {
        foreach ([UserRole::BeaconAdvisor, UserRole::BeaconAdmin, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->enterprise->members()->attach($user->id);
            $request = \Illuminate\Http\Request::create('/');
            $request->setLaravelSession($this->app['session.store']);
            $request->setUserResolver(fn () => $user);
            $shared = app(\App\Http\Middleware\HandleInertiaRequests::class)->share($request);

            $this->assertFalse(($shared['auth']['can']['record_transaction'])(), $role->value);
            $this->assertNull(($shared['auth']['quick_record'])(), $role->value);
        }
    }
}
