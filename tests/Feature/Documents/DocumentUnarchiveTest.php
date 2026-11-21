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

class DocumentUnarchiveTest extends TestCase
{
    use RefreshDatabase;

    private Enterprise $enterprise;

    private User $sme;

    private User $advisor;

    private User $admin;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $this->sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $this->admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $this->enterprise->members()->attach($this->sme->id);
        $this->enterprise->advisors()->attach($this->advisor->id);

        $this->actingAs($this->sme)->post(route('enterprises.documents.store', $this->enterprise), [
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            'title' => 'فاتورة',
        ]);
        $this->document = Document::query()->sole();
    }

    private function archive(): void
    {
        $this->actingAs($this->sme)->post(route('documents.archive', $this->document), ['reason' => 'مستند مكرر'])->assertRedirect();
    }

    public function test_unarchive_clears_archive_fields_returns_to_list_and_is_audited(): void
    {
        $this->archive();
        $this->assertNotNull($this->document->fresh()->archived_at);

        $this->actingAs($this->sme)->post(route('documents.unarchive', $this->document))
            ->assertRedirect(route('documents.show', $this->document))
            ->assertSessionHas('success');

        $doc = $this->document->fresh();
        $this->assertNull($doc->archived_at);
        $this->assertNull($doc->archived_by);
        $this->assertNull($doc->archive_reason);
        Storage::disk('local')->assertExists($doc->path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.unarchived', 'target_id' => (string) $doc->id]);

        $this->get(route('enterprises.documents.index', $this->enterprise))
            ->assertInertia(fn ($page) => $page->has('documents.data', 1));
        // Editing works again.
        $this->put(route('documents.update', $doc), ['title' => 'جديد'])->assertRedirect();
    }

    public function test_cannot_unarchive_a_document_that_is_not_archived(): void
    {
        $this->actingAs($this->sme)->post(route('documents.unarchive', $this->document))->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'document.unarchived']);
    }

    public function test_show_page_exposes_unarchive_only_while_archived_for_allowed_roles(): void
    {
        $this->actingAs($this->sme)->get(route('documents.show', $this->document))
            ->assertInertia(fn ($page) => $page->where('can_unarchive', false));

        $this->archive();

        $this->actingAs($this->sme)->get(route('documents.show', $this->document))
            ->assertInertia(fn ($page) => $page->where('can_unarchive', true)->where('document.is_archived', true));
        $this->actingAs($this->admin)->get(route('documents.show', $this->document))
            ->assertInertia(fn ($page) => $page->where('can_unarchive', true));
        $this->actingAs($this->advisor)->get(route('documents.show', $this->document))
            ->assertInertia(fn ($page) => $page->where('can_unarchive', false));
    }

    public function test_unauthorized_users_cannot_unarchive(): void
    {
        $this->archive();

        $this->actingAs($this->advisor)->post(route('documents.unarchive', $this->document))->assertForbidden();
        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $outsider = User::factory()->create(['role' => $role]);
            $this->actingAs($outsider)->post(route('documents.unarchive', $this->document))->assertForbidden();
        }
        auth()->logout();
        $this->post(route('documents.unarchive', $this->document))->assertRedirect();

        $this->assertNotNull($this->document->fresh()->archived_at);
    }

    public function test_admin_can_unarchive(): void
    {
        $this->archive();

        $this->actingAs($this->admin)->post(route('documents.unarchive', $this->document))->assertRedirect();

        $this->assertNull($this->document->fresh()->archived_at);
    }
}
