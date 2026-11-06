<?php

namespace Database\Seeders;

use App\Enums\AdvisoryRecordStatus;
use App\Enums\UserRole;
use App\Models\AdvisoryActivity;
use App\Models\ComplianceRequirement;
use App\Models\ComplianceRequirementVersion;
use App\Models\EnterpriseComplianceRecord;
use App\Models\FollowUpAction;
use Illuminate\Database\Seeder;

/**
 * Demo compliance deadlines + advisory activity so dashboard status chips and advisor pages have content.
 * Placeholder titles only — not real Syrian legal requirements.
 */
class DemoProgrammeSeeder extends Seeder
{
    public function run(): void
    {
        $admin = DemoUsersSeeder::user(UserRole::BeaconAdmin);
        $advisor = DemoUsersSeeder::user(UserRole::BeaconAdvisor);
        $enterprise = DemoEnterpriseSeeder::enterprise();

        $this->seedCompliance($enterprise, $admin->id);
        $this->seedAdvisory($enterprise, $advisor, $admin->id);
    }

    private function seedCompliance($enterprise, int $adminId): void
    {
        $specs = [
            [
                'code' => 'DEMO-REG-001',
                'category' => 'business_registration',
                'title' => 'السجل التجاري (تجريبي)',
                'guidance' => 'محتوى تجريبي للعرض فقط. المتطلبات الفعلية تعتمدها بيكون.',
                'status' => 'in_progress',
                'due' => now()->addDays(20)->toDateString(),
            ],
            [
                'code' => 'DEMO-TAX-001',
                'category' => 'tax_documentation',
                'title' => 'وثيقة ضريبية أساسية (تجريبي)',
                'guidance' => 'موعد تجريبي قريب لعرض التذكيرات في لوحة التحكم.',
                'status' => 'not_started',
                'due' => now()->addDays(5)->toDateString(),
            ],
            [
                'code' => 'DEMO-LIC-001',
                'category' => 'licensing',
                'title' => 'ترخيص نشاط (تجريبي)',
                'guidance' => 'موعد متأخر تجريبي لعرض حالة الامتثال.',
                'status' => 'not_started',
                'due' => now()->subDays(3)->toDateString(),
            ],
        ];

        foreach ($specs as $spec) {
            $requirement = ComplianceRequirement::query()->firstOrCreate(
                ['code' => $spec['code']],
                ['category' => $spec['category'], 'is_active' => true, 'created_by' => $adminId],
            );

            $version = ComplianceRequirementVersion::query()->firstOrCreate(
                [
                    'compliance_requirement_id' => $requirement->id,
                    'version_number' => 1,
                ],
                [
                    'title_ar' => $spec['title'],
                    'guidance_ar' => $spec['guidance'],
                    'status' => ComplianceRequirementVersion::STATUS_PUBLISHED,
                    'effective_from' => now()->subMonth()->toDateString(),
                    'published_at' => now()->subMonth(),
                    'created_by' => $adminId,
                ],
            );

            EnterpriseComplianceRecord::query()->firstOrCreate(
                [
                    'enterprise_id' => $enterprise->id,
                    'compliance_requirement_id' => $requirement->id,
                ],
                [
                    'compliance_requirement_version_id' => $version->id,
                    'status' => $spec['status'],
                    'due_date' => $spec['due'],
                    'notes' => 'سجل تجريبي للعرض',
                    'updated_by' => $adminId,
                ],
            );
        }
    }

    private function seedAdvisory($enterprise, $advisor, int $adminId): void
    {
        $activity = AdvisoryActivity::query()->firstOrCreate(
            [
                'enterprise_id' => $enterprise->id,
                'notes' => 'زيارة تجريبية: مراجعة تسجيل المبيعات اليومية وشرح زر «سجّل معاملة».',
            ],
            [
                'activity_date' => now()->subDays(4)->toDateString(),
                'responsible_user_id' => $advisor->id,
                'target_date' => now()->addDays(10)->toDateString(),
                'status' => AdvisoryRecordStatus::Open,
                'created_by' => $adminId,
            ],
        );

        FollowUpAction::query()->firstOrCreate(
            [
                'enterprise_id' => $enterprise->id,
                'description' => 'إكمال بيانات الملف وإرفاق صورة الرخصة (تجريبي)',
            ],
            [
                'advisory_activity_id' => $activity->id,
                'responsible_user_id' => DemoUsersSeeder::user(UserRole::SmeUser)->id,
                'target_date' => now()->addDays(7)->toDateString(),
                'status' => AdvisoryRecordStatus::Open,
                'created_by' => $advisor->id,
            ],
        );
    }
}
