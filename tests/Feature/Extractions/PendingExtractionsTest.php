<?php

namespace Tests\Feature\Extractions;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\ReceiptExtraction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PendingExtractionsTest extends TestCase
{
    use RefreshDatabase;

    private Enterprise $mine;
    private Enterprise $other;
    private User $sme;
    private User $foreign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->mine = Enterprise::query()->create(['business_name' => 'منشأتي', 'location' => 'damascus']);
        $this->other = Enterprise::query()->create(['business_name' => 'غيري', 'location' => 'aleppo']);
        $this->sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->foreign = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->mine->members()->attach($this->sme->id);
        $this->other->members()->attach($this->foreign->id);
    }

    private function extraction(Enterprise $e, string $status = 'pending_review', string $title = 'r'): ReceiptExtraction
    {
        $doc = new Document;
        $doc->forceFill(['enterprise_id' => $e->id, 'title' => $title, 'original_name' => 'r.jpg', 'disk' => 'local', 'path' => 'x', 'mime_type' => 'image/jpeg', 'size' => 1])->save();
        $x = new ReceiptExtraction;
        $x->forceFill(['enterprise_id' => $e->id, 'document_id' => $doc->id, 'status' => $status, 'extractor' => 'fake'])->save();

        return $x;
    }

    public function test_lists_only_own_pending_extractions(): void
    {
        $pending = $this->extraction($this->mine, title: 'mine-pending');
        $confirmed = $this->extraction($this->mine, 'confirmed');
        $rejected = $this->extraction($this->mine, 'rejected');
        $foreign = $this->extraction($this->other, title: 'foreign-pending');

        $this->actingAs($this->sme)->get(route('enterprises.extractions.pending', $this->mine))
            ->assertOk()
            ->assertDontSee($foreign->public_id)
            ->assertDontSee($confirmed->public_id)
            ->assertDontSee($rejected->public_id)
            ->assertInertia(fn (Assert $page) => $page->component('Extractions/Pending')
                ->has('extractions.data', 1)
                ->where('extractions.data.0.public_id', $pending->public_id)
                ->where('extractions.meta.total', 1)
                ->has('extractions.links')
            );

        $this->actingAs($this->sme)->get(route('extractions.show', $pending))->assertOk();
    }

    public function test_user_of_another_enterprise_is_forbidden_and_sees_no_rows(): void
    {
        $mine = $this->extraction($this->mine);

        $response = $this->actingAs($this->foreign)->get(route('enterprises.extractions.pending', $this->mine));
        $this->assertContains($response->status(), [403, 404]);
        $response->assertDontSee($mine->public_id);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('enterprises.extractions.pending', $this->mine))->assertRedirect('/login');
    }
}
