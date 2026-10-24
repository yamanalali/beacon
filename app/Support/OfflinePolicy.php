<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\ComplianceRequirement;
use App\Models\Enterprise;
use App\Models\EnterpriseComplianceRecord;
use App\Models\FinancialTransaction;
use App\Models\FollowUpAction;
use App\Models\ReadinessAssessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class OfflinePolicy
{
    // Deliberately exclude account/security, administration of users, backups,
    // external sharing, consent and final approvals. Those need a live review.
    public const WRITES = [
        'enterprises.store', 'enterprises.update',
        'enterprises.transactions.store', 'transactions.correct', 'transactions.settlements.store',
        'enterprises.documents.store', 'documents.update', 'documents.archive',
        'enterprises.advisory.store', 'enterprises.follow-ups.store', 'follow-ups.complete',
        'enterprises.compliance.update', 'enterprises.readiness.store', 'readiness-assessments.results.update',
        'enterprises.referrals.store', 'referrals.status', 'referrals.outcome',
    ];

    public static function scope(User $user): string
    {
        return hash_hmac('sha256', 'offline-user:'.$user->id, (string) config('app.key'));
    }

    /** Cache authorization changes with role, password, membership and referral consent. */
    public static function authorization(User $user): string
    {
        $links = match ($user->role) {
            UserRole::SmeUser => DB::table('enterprise_members')->where('user_id', $user->id)->orderBy('enterprise_id')->pluck('enterprise_id')->all(),
            UserRole::BeaconAdvisor => DB::table('advisor_assignments')->where('user_id', $user->id)->orderBy('enterprise_id')->pluck('enterprise_id')->all(),
            UserRole::MfiUser => DB::table('referrals')->leftJoin('referral_consents', 'referrals.id', '=', 'referral_consents.referral_id')
                ->where('referrals.mfi_institution_id', $user->mfi_institution_id)->orderBy('referrals.id')->orderBy('referral_consents.id')
                ->get(['referrals.id', 'referrals.status', 'referrals.disclosed_items', 'referral_consents.id as consent_id', 'referral_consents.status as consent_status', 'referral_consents.scope'])->all(),
            default => [],
        };

        return hash('sha256', AccountSecurity::stamp($user).json_encode($links, JSON_THROW_ON_ERROR));
    }

    public static function versions(Request $request, bool $nested = false): array
    {
        $versions = [];
        $models = array_values(array_filter($request->route()?->parameters() ?? [], fn ($value) => $value instanceof Model));
        foreach ($models as $model) {
            if ($nested && $model instanceof ReadinessAssessment) {
                array_push($models, ...$model->results()->get()->all());
            }
            if ($model instanceof Enterprise && str_starts_with($request->route()->getName() ?? '', 'enterprises.compliance.')) {
                if ($nested) {
                    array_push($models, ...ComplianceRequirement::query()->get()->all());
                }
            }
            if ($nested && $model instanceof Enterprise && $request->route()->getName() === 'enterprises.advisory.index') {
                array_push($models, ...FollowUpAction::query()->where('enterprise_id', $model->id)->get()->all());
            }
        }
        foreach ($models as $model) {
            $data = $model->getAttributes();
            if ($model instanceof FinancialTransaction) {
                $data['settlements'] = $model->settlements()->orderBy('id')->get()->map->getAttributes()->all();
            }
            if ($model instanceof Enterprise && str_starts_with($request->route()->getName() ?? '', 'enterprises.compliance.')) {
                $data['compliance_records'] = EnterpriseComplianceRecord::query()->where('enterprise_id', $model->id)->orderBy('id')->get()->map->getAttributes()->all();
            }
            $versions[$model->getMorphClass().':'.$model->getKey()] = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        }

        return $versions;
    }

    public static function fingerprint(Request $request): string
    {
        $normalize = function ($value) use (&$normalize) {
            if ($value instanceof UploadedFile) {
                return ['file' => hash_file('sha256', $value->getRealPath()), 'name' => $value->getClientOriginalName()];
            }
            if (is_array($value)) {
                ksort($value);

                return array_map($normalize, $value);
            }

            return $value;
        };

        return hash('sha256', json_encode([$request->method(), $request->getPathInfo(), $request->getQueryString(), $normalize($request->except(['_token', '_beacon_versions']))], JSON_THROW_ON_ERROR));
    }
}
