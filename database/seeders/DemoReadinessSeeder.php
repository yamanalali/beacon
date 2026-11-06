<?php

namespace Database\Seeders;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Readiness\SnapshotAssessmentScore;
use App\Enums\UserRole;
use App\Models\ReadinessAssessment;
use App\Models\ReadinessCriterion;
use App\Models\ReadinessRuleSet;
use App\Support\ReadinessScorer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ensures a published demo rule set exists and creates a finalized assessment for the demo enterprise.
 */
class DemoReadinessSeeder extends Seeder
{
    public const RULE_SET_NAME = 'قواعد جاهزية تجريبية (عرض)';

    public function run(): void
    {
        $admin = DemoUsersSeeder::user(UserRole::BeaconAdmin);
        $advisor = DemoUsersSeeder::user(UserRole::BeaconAdvisor);
        $enterprise = DemoEnterpriseSeeder::enterprise();

        $ruleSet = $this->ensurePublishedRuleSet($admin->id);

        if (ReadinessAssessment::query()->where('enterprise_id', $enterprise->id)->exists()) {
            return;
        }

        DB::transaction(function () use ($enterprise, $ruleSet, $advisor, $admin) {
            $assessment = ReadinessAssessment::query()->create([
                'enterprise_id' => $enterprise->id,
                'readiness_rule_set_id' => $ruleSet->id,
                'status' => ReadinessAssessment::STATUS_DRAFT,
                'assessed_by' => $advisor->id,
            ]);

            $sme = DemoUsersSeeder::user(UserRole::SmeUser);

            foreach ($ruleSet->criteria as $criterion) {
                $outcome = match ($criterion->code) {
                    'demo_books' => 'met',
                    'demo_license' => 'not_met',
                    'demo_records' => 'met',
                    default => 'met',
                };

                $assessment->results()->create([
                    'readiness_criterion_id' => $criterion->id,
                    'outcome' => $outcome,
                    'note' => $outcome === 'met' ? 'مستوفى في العرض التجريبي' : 'يحتاج متابعة',
                    'action_needed' => $outcome === 'not_met' ? 'إكمال الترخيص ورفع الوثيقة' : null,
                    'responsible_user_id' => $outcome === 'not_met' ? $sme->id : null,
                    'target_date' => $outcome === 'not_met' ? now()->addDays(14)->toDateString() : null,
                ]);
            }

            $evaluation = app(ReadinessScorer::class)->evaluate($assessment->fresh(['results.criterion', 'ruleSet.criteria']));
            $assessment->update([
                'status' => ReadinessAssessment::STATUS_FINALIZED,
                'score' => $evaluation['score'],
                'mandatory_conditions_met' => $evaluation['mandatory_conditions_met'],
                'summary' => $evaluation,
                'finalized_at' => now(),
            ]);
            app(SnapshotAssessmentScore::class)->initial($assessment->fresh(), $advisor, $evaluation);
            app(RecordAuditLog::class)->record($admin, 'readiness.assessment_finalized', $assessment, [
                'score' => $evaluation['score'],
                'demo' => true,
            ]);
        });
    }

    private function ensurePublishedRuleSet(int $adminId): ReadinessRuleSet
    {
        $existing = ReadinessRuleSet::current();
        if ($existing !== null && $existing->criteria()->exists()) {
            return $existing->load('criteria');
        }

        $set = ReadinessRuleSet::query()
            ->where('name_ar', self::RULE_SET_NAME)
            ->orderByDesc('version_number')
            ->first();

        if ($set !== null && $set->status !== ReadinessRuleSet::STATUS_DRAFT && $set->criteria()->exists()) {
            return $set->load('criteria');
        }

        if ($set === null || $set->status !== ReadinessRuleSet::STATUS_DRAFT) {
            $set = ReadinessRuleSet::query()->create([
                'name_ar' => self::RULE_SET_NAME,
                'version_number' => ((int) ReadinessRuleSet::query()->max('version_number')) + 1,
                'status' => ReadinessRuleSet::STATUS_DRAFT,
                'change_summary' => 'قواعد تجريبية للعرض فقط — ليست قواعد بيكون المعتمدة.',
                'created_by' => $adminId,
            ]);
        }

        if (! $set->pillars()->exists()) {
            $finance = $set->pillars()->create([
                'code' => 'finance', 'label_ar' => 'السجلات المالية', 'weight' => 50, 'sort_order' => 1,
            ]);
            $compliance = $set->pillars()->create([
                'code' => 'compliance', 'label_ar' => 'الامتثال', 'weight' => 50, 'sort_order' => 2,
            ]);
        } else {
            $finance = $set->pillars()->where('code', 'finance')->first() ?? $set->pillars()->first();
            $compliance = $set->pillars()->where('code', 'compliance')->first() ?? $set->pillars()->skip(1)->first() ?? $finance;
        }

        if (! $set->criteria()->exists()) {
            $set->criteria()->create([
                'code' => 'demo_books',
                'label_ar' => 'هل توجد سجلات دخل ومصروف؟',
                'weight' => 2,
                'is_mandatory' => true,
                'missing_data_treatment' => 'counts_as_unmet',
                'type' => ReadinessCriterion::TYPE_BOOLEAN,
                'readiness_pillar_id' => $finance->id,
                'sort_order' => 1,
            ]);
            $set->criteria()->create([
                'code' => 'demo_records',
                'label_ar' => 'اكتمال المعاملات هذا الشهر',
                'weight' => 1,
                'is_mandatory' => false,
                'missing_data_treatment' => 'excluded',
                'type' => ReadinessCriterion::TYPE_BOOLEAN,
                'readiness_pillar_id' => $finance->id,
                'sort_order' => 2,
            ]);
            $set->criteria()->create([
                'code' => 'demo_license',
                'label_ar' => 'هل الترخيص مكتمل؟',
                'weight' => 2,
                'is_mandatory' => true,
                'missing_data_treatment' => 'needs_review',
                'type' => ReadinessCriterion::TYPE_BOOLEAN,
                'readiness_pillar_id' => $compliance->id,
                'sort_order' => 3,
            ]);
        }

        if (! $set->bands()->exists()) {
            $set->bands()->create(['label_ar' => 'منخفضة', 'min' => 0, 'max' => 49.99, 'color_token' => 'danger', 'sort_order' => 1]);
            $set->bands()->create(['label_ar' => 'متوسطة', 'min' => 50, 'max' => 74.99, 'color_token' => 'warning', 'sort_order' => 2]);
            $set->bands()->create(['label_ar' => 'مرتفعة', 'min' => 75, 'max' => 100, 'color_token' => 'success', 'sort_order' => 3]);
        }

        $set->update([
            'status' => ReadinessRuleSet::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by' => $adminId,
        ]);

        return $set->fresh(['criteria']);
    }
}
