<?php

namespace App\Dashboard;

use App\Ai\Models\AiEscalation;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\EnterpriseComplianceRecord;
use App\Models\Referral;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Programme-level props for the Beacon dashboard (§38/§39).
 * Scope follows EnterprisePolicy::view: admins see every enterprise,
 * advisors only enterprises they are assigned to (advisor_assignments).
 */
class BeaconDashboardData
{
    /** @return array<string, mixed> */
    public function build(User $user, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $isAdmin = $user->role === UserRole::BeaconAdmin;

        $enterpriseIds = fn (): Builder => $isAdmin
            ? Enterprise::query()->select('id')
            : Enterprise::query()->select('id')->whereHas('advisors', fn ($q) => $q->whereKey($user->id));

        $referrals = Referral::query()->whereIn('enterprise_id', $enterpriseIds());
        $byStatus = (clone $referrals)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $recentReferrals = (clone $referrals)->with(['enterprise:id,public_id,business_name', 'institution:id,name_ar'])
            ->orderByDesc('updated_at')->orderByDesc('id')->limit(5)->get()
            ->map(fn (Referral $r) => [
                'public_id' => $r->public_id,
                'status' => $r->status,
                'status_label' => $r->statusLabel(),
                'enterprise' => ['public_id' => $r->enterprise?->public_id, 'business_name' => $r->enterprise?->business_name],
                'institution' => $r->institution?->name_ar,
                'updated_at' => $r->updated_at?->toIso8601String(),
            ])->values()->all();

        return [
            'scope' => $isAdmin ? 'all' : 'assigned',
            'kpis' => [
                'enterprises_count' => Enterprise::query()->whereIn('id', $enterpriseIds())->count(),
                // TODO: BUSINESS DECISION REQUIRED - definition of an "active" enterprise.
                'active_enterprises_count' => null,
                // TODO: BUSINESS DECISION REQUIRED - readiness score bands (none defined; see ReadinessScorer).
                'enterprises_by_readiness_band' => null,
                // TODO: BUSINESS DECISION REQUIRED - escalation status set (only "open" exists today).
                'open_escalations_count' => AiEscalation::query()
                    ->whereIn('enterprise_id', $enterpriseIds())
                    ->where('status', 'open')->count(),
                'overdue_compliance_count' => EnterpriseComplianceRecord::query()
                    ->whereIn('enterprise_id', $enterpriseIds())
                    ->whereNotNull('due_date')
                    ->forCurrentVersion()->where('status', '!=', 'completed')
                    ->where('due_date', '<', $today->toDateString())->count(),
                'referrals_by_status' => collect(array_keys(Referral::STATUS_LABELS))
                    ->map(fn (string $s) => ['status' => $s, 'label' => Referral::STATUS_LABELS[$s], 'count' => (int) ($byStatus[$s] ?? 0)])
                    ->all(),
            ],
            'recent_referrals' => $recentReferrals,
        ];
    }
}
