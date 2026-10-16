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

class DocumentManagementTest extends TestCase
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

    public function test_detail_page_shows_history_for_authorized_roles_only(): void
    {
        foreach ([$this->sme, $this->advisor, $this->admin] as $user) {
            $this->actingAs($user)->get(route('documents.show', $this->document))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('Documents/Show')->where('history.0.action', 'document.uploaded'));
        }

        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $outsider = User::factory()->create(['role' => $role]);
            $this->actingAs($outsider)->get(route('documents.show', $this->document))->assertForbidden();
            $this->actingAs($outsider)->put(route('documents.update', $this->document), ['title' => 'x'])->assertForbidden();
            $this->actingAs($outsider)->post(route('documents.archive', $this->document), ['reason' => 'xyz'])->assertForbidden();
        }
    }

    public function test_metadata_update_is_audited_with_before_and_after(): void
    {
        $this->actingAs($this->sme)->put(route('documents.update', $this->document), ['title' => 'إيصال كهرباء'])->assertRedirect();

        $this->assertSame('إيصال كهرباء', $this->document->fresh()->title);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.metadata_updated', 'target_id' => (string) $this->document->id]);
    }

    public function test_archive_keeps_file_hides_from_list_and_requires_reason(): void
    {
        $this->actingAs($this->sme)->post(route('documents.archive', $this->document), [])->assertSessionHasErrors('reason');

        $this->actingAs($this->sme)->post(route('documents.archive', $this->document), ['reason' => 'مستند مكرر'])->assertRedirect();

        $doc = $this->document->fresh();
        $this->assertNotNull($doc->archived_at);
        Storage::disk('local')->assertExists($doc->path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.archived']);

        $this->actingAs($this->sme)->get(route('enterprises.documents.index', $this->enterprise))
            ->assertInertia(fn ($page) => $page->has('documents.data', 0));

        // Archived documents cannot be edited or archived again.
        $this->actingAs($this->sme)->put(route('documents.update', $doc), ['title' => 'x'])->assertForbidden();
        $this->actingAs($this->admin)->post(route('documents.archive', $doc), ['reason' => 'again'])->assertForbidden();
    }

    public function test_advisor_cannot_archive_but_admin_can(): void
    {
        $this->actingAs($this->advisor)->post(route('documents.archive', $this->document), ['reason' => 'xyz'])->assertForbidden();
        $this->actingAs($this->admin)->post(route('documents.archive', $this->document), ['reason' => 'xyz'])->assertRedirect();
    }
}
