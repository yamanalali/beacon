<?php

namespace Tests\Feature\Readiness;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ComplianceSubmission;
use App\Models\EnterpriseComplianceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplianceSubmissionTest extends TestCase
{
    use ReadinessTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
        Storage::fake(config('documents.disk'));
    }

    public function test_sme_can_submit_document_and_advisor_can_approve(): void
    {
        $enterprise = $this->enterprise();
        $requirement = $this->publishedRequirement('DOC-1', true);
        $sme = $this->smeOf($enterprise);
        $advisor = $this->advisorOf($enterprise);

        $this->actingAs($sme)->post(route('enterprises.compliance.submissions.store', [$enterprise, $requirement]), [
            'file' => UploadedFile::fake()->create('license.pdf', 120, 'application/pdf'),
            'title' => 'رخصة تجريبية',
        ])->assertRedirect(route('enterprises.compliance.index', $enterprise));

        $record = EnterpriseComplianceRecord::query()->sole();
        $this->assertSame(EnterpriseComplianceRecord::STATUS_AWAITING_REVIEW, $record->status);
        $submission = ComplianceSubmission::query()->sole();
        $this->assertSame(ComplianceSubmission::REVIEW_PENDING, $submission->review_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'compliance.document_submitted', 'actor_id' => $sme->id]);

        $this->actingAs($advisor)->post(route('enterprises.compliance.submissions.review', [$enterprise, $requirement, $submission]), [
            'decision' => 'approve',
            'review_note' => 'وثيقة واضحة',
        ])->assertRedirect(route('enterprises.compliance.index', $enterprise));

        $this->assertSame(EnterpriseComplianceRecord::STATUS_COMPLETED, $record->fresh()->status);
        $this->assertSame(ComplianceSubmission::REVIEW_APPROVED, $submission->fresh()->review_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'compliance.submission_reviewed', 'actor_id' => $advisor->id]);
    }

    public function test_sme_cannot_review_and_unassigned_advisor_forbidden(): void
    {
        $enterprise = $this->enterprise();
        $other = $this->enterprise('أخرى');
        $requirement = $this->publishedRequirement('DOC-2', true);
        $sme = $this->smeOf($enterprise);
        $outsider = $this->advisorOf($other);

        $this->actingAs($sme)->post(route('enterprises.compliance.submissions.store', [$enterprise, $requirement]), [
            'file' => UploadedFile::fake()->image('scan.jpg'),
        ])->assertRedirect();

        $submission = ComplianceSubmission::query()->sole();

        $this->actingAs($sme)->post(route('enterprises.compliance.submissions.review', [$enterprise, $requirement, $submission]), [
            'decision' => 'approve',
        ])->assertForbidden();

        $this->actingAs($outsider)->post(route('enterprises.compliance.submissions.review', [$enterprise, $requirement, $submission]), [
            'decision' => 'reject',
        ])->assertForbidden();
    }

    public function test_reject_and_request_resubmission_allow_new_upload(): void
    {
        $enterprise = $this->enterprise();
        $requirement = $this->publishedRequirement('DOC-3', true);
        $sme = $this->smeOf($enterprise);
        $advisor = $this->advisorOf($enterprise);

        $this->actingAs($sme)->post(route('enterprises.compliance.submissions.store', [$enterprise, $requirement]), [
            'file' => UploadedFile::fake()->create('a.pdf', 80, 'application/pdf'),
        ]);
        $first = ComplianceSubmission::query()->sole();

        $this->actingAs($advisor)->post(route('enterprises.compliance.submissions.review', [$enterprise, $requirement, $first]), [
            'decision' => 'reject',
            'review_note' => 'غير واضحة',
        ])->assertRedirect();
        $this->assertSame(EnterpriseComplianceRecord::STATUS_REJECTED, EnterpriseComplianceRecord::query()->sole()->status);

        $this->actingAs($sme)->post(route('enterprises.compliance.submissions.store', [$enterprise, $requirement]), [
            'file' => UploadedFile::fake()->create('b.pdf', 90, 'application/pdf'),
        ])->assertRedirect();
        $second = ComplianceSubmission::query()->orderByDesc('id')->first();
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(EnterpriseComplianceRecord::STATUS_AWAITING_REVIEW, EnterpriseComplianceRecord::query()->sole()->status);

        $this->actingAs($advisor)->post(route('enterprises.compliance.submissions.review', [$enterprise, $requirement, $second]), [
            'decision' => 'request_resubmission',
        ])->assertRedirect();
        $this->assertSame(EnterpriseComplianceRecord::STATUS_NEEDS_RESUBMISSION, EnterpriseComplianceRecord::query()->sole()->status);

        $this->actingAs($sme)->post(route('enterprises.compliance.submissions.store', [$enterprise, $requirement]), [
            'file' => UploadedFile::fake()->create('c.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame(3, ComplianceSubmission::query()->count());
    }

    public function test_sme_cannot_submit_for_another_enterprise(): void
    {
        $mine = $this->enterprise();
        $theirs = $this->enterprise('منشأة أخرى');
        $requirement = $this->publishedRequirement('DOC-4', true);
        $sme = $this->smeOf($mine);

        $this->actingAs($sme)->post(route('enterprises.compliance.submissions.store', [$theirs, $requirement]), [
            'file' => UploadedFile::fake()->create('x.pdf', 50, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_document_required_item_cannot_be_completed_via_status_put(): void
    {
        $enterprise = $this->enterprise();
        $requirement = $this->publishedRequirement('DOC-5', true);
        $advisor = $this->advisorOf($enterprise);

        $this->actingAs($advisor)->put(route('enterprises.compliance.update', [$enterprise, $requirement]), [
            'status' => 'completed',
        ])->assertSessionHasErrors('status');
    }

    public function test_index_exposes_upload_and_review_flags(): void
    {
        $enterprise = $this->enterprise();
        $requirement = $this->publishedRequirement('DOC-6', true);
        $sme = $this->smeOf($enterprise);
        $advisor = $this->advisorOf($enterprise);

        $this->actingAs($sme)->get(route('enterprises.compliance.index', $enterprise))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canSubmit', true)
                ->where('canReview', false)
                ->where('items.0.can_upload', true)
                ->where('items.0.requires_document', true));

        $this->actingAs($sme)->post(route('enterprises.compliance.submissions.store', [$enterprise, $requirement]), [
            'file' => UploadedFile::fake()->create('wait.pdf', 40, 'application/pdf'),
        ]);

        $this->actingAs($advisor)->get(route('enterprises.compliance.index', $enterprise))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canReview', true)
                ->where('items.0.can_review', true)
                ->where('items.0.status', EnterpriseComplianceRecord::STATUS_AWAITING_REVIEW)
                ->where('items.0.latest_submission.review_status', ComplianceSubmission::REVIEW_PENDING));

        $this->assertSame(1, AuditLog::query()->where('action', 'compliance.document_submitted')->count());
    }
}
