<?php

namespace Tests\Feature\Readiness;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ComplianceRequirement;
use App\Models\ComplianceRequirementVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
    }

    public function test_only_beacon_admin_can_manage_compliance_configuration(): void
    {
        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->get(route('admin.compliance.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.compliance.store'), ['code' => 'X'.$role->value, 'category' => 'licensing', 'title_ar' => 'س'])->assertForbidden();
        }
        $this->assertDatabaseCount('compliance_requirements', 0);

        $this->actingAs($this->user(UserRole::BeaconAdmin))->get(route('admin.compliance.index'))->assertOk();
    }

    public function test_admin_creates_publishes_and_versions_requirement_without_rewriting_history(): void
    {
        $admin = $this->user(UserRole::BeaconAdmin);

        $this->actingAs($admin)->post(route('admin.compliance.store'), [
            'code' => 'LIC-1', 'category' => 'licensing', 'title_ar' => 'رخصة مزاولة', 'requires_document' => true,
        ])->assertRedirect(route('admin.compliance.index'));

        $requirement = ComplianceRequirement::query()->firstOrFail();
        $v1 = $requirement->versions()->firstOrFail();
        $this->assertTrue($v1->isGuidancePending());

        $this->actingAs($admin)->post(route('admin.compliance.versions.publish', $v1))->assertRedirect();
        $this->assertSame('published', $v1->fresh()->status);

        // published versions are immutable
        $this->actingAs($admin)->put(route('admin.compliance.versions.update', $v1), ['title_ar' => 'تعديل'])->assertStatus(422);
        $this->expectExceptionOnUpdate($v1->fresh());

        $this->actingAs($admin)->post(route('admin.compliance.versions.store', $requirement), [
            'title_ar' => 'رخصة مزاولة محدثة', 'change_summary' => 'تحديث الإرشاد',
        ])->assertRedirect();
        $v2 = $requirement->versions()->where('version_number', 2)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.compliance.versions.publish', $v2));

        $this->assertSame('رخصة مزاولة', $v1->fresh()->title_ar);
        $this->assertSame(2, $requirement->fresh()->currentVersion()->version_number);
        $this->assertTrue(AuditLog::query()->where('action', 'compliance.version_published')->count() === 2);
        $published = AuditLog::query()->where('action', 'compliance.version_published')->latest('id')->first();
        $this->assertSame($admin->id, $published->actor_id);
        $this->assertSame('تحديث الإرشاد', $published->context['change_summary']);
    }

    public function test_admin_can_deactivate_and_replace_requirement(): void
    {
        $admin = $this->user(UserRole::BeaconAdmin);
        $old = $this->publishedRequirement('OLD');
        $new = $this->publishedRequirement('NEW');

        $this->actingAs($admin)->post(route('admin.compliance.replace', $old), ['replacement_public_id' => $new->public_id])->assertRedirect();
        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame($new->id, $old->fresh()->replaced_by_id);

        $this->actingAs($admin)->post(route('admin.compliance.deactivate', $new))->assertRedirect();
        $this->assertFalse($new->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'compliance.requirement_replaced']);
    }

    public function test_enterprise_compliance_visibility_per_role(): void
    {
        $enterprise = $this->enterprise();
        $other = $this->enterprise('منشأة أخرى');
        $this->publishedRequirement();
        $sme = $this->smeOf($enterprise);
        $advisor = $this->advisorOf($enterprise);

        $this->actingAs($sme)->get(route('enterprises.compliance.index', $enterprise))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Compliance/Enterprise')->where('canUpdate', false)->where('items.0.missing_document', true));
        $this->actingAs($advisor)->get(route('enterprises.compliance.index', $enterprise))->assertOk()
            ->assertInertia(fn ($page) => $page->where('canUpdate', true));
        $this->actingAs($this->user(UserRole::BeaconAdmin))->get(route('enterprises.compliance.index', $enterprise))->assertOk();

        $this->actingAs($sme)->get(route('enterprises.compliance.index', $other))->assertForbidden();
        $this->actingAs($advisor)->get(route('enterprises.compliance.index', $other))->assertForbidden();
        $this->actingAs($this->user(UserRole::MfiUser))->get(route('enterprises.compliance.index', $enterprise))->assertForbidden();
        $this->actingAs($this->user(UserRole::TechnicalAdmin))->get(route('enterprises.compliance.index', $enterprise))->assertForbidden();
    }

    public function test_status_update_records_version_and_is_staff_only(): void
    {
        $enterprise = $this->enterprise();
        $requirement = $this->publishedRequirement();
        $sme = $this->smeOf($enterprise);
        $advisor = $this->advisorOf($enterprise);

        $this->actingAs($sme)->put(route('enterprises.compliance.update', [$enterprise, $requirement]), ['status' => 'completed'])->assertForbidden();
        $this->actingAs($this->user(UserRole::BeaconAdvisor))->put(route('enterprises.compliance.update', [$enterprise, $requirement]), ['status' => 'completed'])->assertForbidden();

        $this->actingAs($advisor)->put(route('enterprises.compliance.update', [$enterprise, $requirement]), ['status' => 'completed', 'due_date' => '2026-12-01'])->assertRedirect();
        $this->assertDatabaseHas('enterprise_compliance_records', [
            'enterprise_id' => $enterprise->id, 'status' => 'completed',
            'compliance_requirement_version_id' => $requirement->currentVersion()->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'compliance.enterprise_status_updated', 'actor_id' => $advisor->id]);
    }

    private function expectExceptionOnUpdate(ComplianceRequirementVersion $version): void
    {
        try {
            $version->update(['title_ar' => 'محاولة']);
            $this->fail('Published version was modified.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
    }
}
