<?php

namespace Tests\Feature\Readiness;

use App\Enums\UserRole;
use App\Models\ComplianceRequirement;
use App\Models\ComplianceRequirementVersion;
use App\Models\Enterprise;
use App\Models\MfiInstitution;
use App\Models\ReadinessRuleSet;
use App\Models\User;

trait ReadinessTestHelpers
{
    protected function setUpReadiness(): void
    {
        $this->withoutVite();
    }

    protected function user(UserRole $role, array $attributes = []): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->forceFill(['is_active' => true] + $attributes)->save();

        return $user->fresh();
    }

    protected function enterprise(string $name = 'منشأة اختبار'): Enterprise
    {
        return Enterprise::query()->create(['business_name' => $name, 'location' => 'damascus']);
    }

    protected function smeOf(Enterprise $enterprise): User
    {
        $sme = $this->user(UserRole::SmeUser);
        $enterprise->members()->attach($sme->id);

        return $sme;
    }

    protected function advisorOf(Enterprise $enterprise): User
    {
        $advisor = $this->user(UserRole::BeaconAdvisor);
        $enterprise->advisors()->attach($advisor->id);

        return $advisor;
    }

    protected function publishedRequirement(string $code = 'REQ-1', bool $requiresDocument = true): ComplianceRequirement
    {
        $requirement = ComplianceRequirement::query()->create(['code' => $code, 'category' => 'licensing']);
        $requirement->versions()->create([
            'version_number' => 1, 'title_ar' => 'متطلب '.$code, 'requires_document' => $requiresDocument,
            'status' => ComplianceRequirementVersion::STATUS_PUBLISHED, 'published_at' => now(),
        ]);

        return $requirement;
    }

    /** @param list<array<string,mixed>> $criteria */
    protected function publishedRuleSet(array $criteria, int $version = 1): ReadinessRuleSet
    {
        $set = ReadinessRuleSet::query()->create(['version_number' => $version, 'name_ar' => 'قواعد '.$version, 'status' => ReadinessRuleSet::STATUS_DRAFT]);
        foreach ($criteria as $c) {
            $set->criteria()->create($c + ['label_ar' => $c['code'], 'missing_data_treatment' => 'counts_as_unmet', 'is_mandatory' => false]);
        }
        $set->update(['status' => ReadinessRuleSet::STATUS_PUBLISHED, 'published_at' => now()]);

        return $set;
    }

    protected function institution(string $name = 'مؤسسة أ'): MfiInstitution
    {
        return MfiInstitution::query()->create(['name_ar' => $name]);
    }

    protected function mfiUserOf(MfiInstitution $institution): User
    {
        return $this->user(UserRole::MfiUser, ['mfi_institution_id' => $institution->id]);
    }
}
