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
 * §38 indicator formulas remain empty until Beacon defines them.
 */
class BeaconDashboardData
{
    /**
     * @param  array{from?: string|null, to?: string|null, sector?: string|null, region?: string|null, gender?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function build(User $user, array $filters = [], ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $filters = $this->normalizeFilters($filters);
        $enterpriseIds = fn (): Builder => $this->enterpriseScope($user, $filters)->select('id');

        $referrals = Referral::query()->whereIn('enterprise_id', $enterpriseIds());
        if ($filters['from'] !== null) {
            $referrals->whereDate('updated_at', '>=', $filters['from']);
        }
        if ($filters['to'] !== null) {
            $referrals->whereDate('updated_at', '<=', $filters['to']);
        }

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

        $compliance = EnterpriseComplianceRecord::query()
            ->whereIn('enterprise_id', $enterpriseIds())
            ->whereNotNull('due_date')
            ->forCurrentVersion()->where('status', '!=', 'completed')
            ->where('due_date', '<', $today->toDateString());
        if ($filters['from'] !== null) {
            $compliance->whereDate('due_date', '>=', $filters['from']);
        }
        if ($filters['to'] !== null) {
            $compliance->whereDate('due_date', '<=', $filters['to']);
        }

        return [
            'scope' => $user->role === UserRole::BeaconAdmin ? 'all' : 'assigned',
            'kpis' => [
                'enterprises_count' => $this->enterpriseScope($user, $filters)->count(),
                // TODO: BUSINESS DECISION REQUIRED - definition of an "active" enterprise.
                'active_enterprises_count' => null,
                // TODO: BUSINESS DECISION REQUIRED - readiness score bands (none defined; see ReadinessScorer).
                'enterprises_by_readiness_band' => null,
                // TODO: BUSINESS DECISION REQUIRED - escalation status set (only "open" exists today).
                'open_escalations_count' => AiEscalation::query()
                    ->whereIn('enterprise_id', $enterpriseIds())
                    ->where('status', 'open')->count(),
                'overdue_compliance_count' => $compliance->count(),
                'referrals_by_status' => collect(array_keys(Referral::STATUS_LABELS))
                    ->map(fn (string $s) => ['status' => $s, 'label' => Referral::STATUS_LABELS[$s], 'count' => (int) ($byStatus[$s] ?? 0)])
                    ->all(),
            ],
            'recent_referrals' => $recentReferrals,
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null, sector?: string|null, region?: string|null, gender?: string|null}  $filters
     * @return array{sectors: list<array{value: string, label: string}>, regions: list<array{value: string, label: string}>, genders: list<array{value: string, label: string}>}
     */
    public function filterOptions(User $user, array $filters = []): array
    {
        $base = $this->enterpriseScope($user, $this->normalizeFilters(array_diff_key($filters, array_flip(['sector', 'region', 'gender']))));

        $sectors = (clone $base)->whereNotNull('sector')->where('sector', '!=', '')
            ->distinct()->orderBy('sector')->pluck('sector')
            ->map(fn (string $s) => ['value' => $s, 'label' => $s])->values()->all();

        $locationLabels = [
            'damascus' => 'دمشق',
            'rural_damascus' => 'ريف دمشق',
            'daraa' => 'درعا',
        ];
        $regions = (clone $base)->whereNotNull('location')->where('location', '!=', '')
            ->distinct()->orderBy('location')->pluck('location')
            ->map(fn (string $loc) => ['value' => $loc, 'label' => $locationLabels[$loc] ?? $loc])->values()->all();

        return [
            'sectors' => $sectors,
            'regions' => $regions,
            'genders' => [
                ['value' => 'female', 'label' => 'إناث فقط (حسب عدد الموظفات)'],
                ['value' => 'male', 'label' => 'ذكور فقط (حسب عدد الموظفين)'],
                ['value' => 'mixed', 'label' => 'مختلط'],
                ['value' => 'unknown', 'label' => 'غير محدد'],
            ],
        ];
    }

    /**
     * CSV rows matching the on-screen programme KPIs (same calculation as build()).
     *
     * @param  array{from?: string|null, to?: string|null, sector?: string|null, region?: string|null, gender?: string|null}  $filters
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    public function exportRows(User $user, array $filters = []): array
    {
        $programme = $this->build($user, $filters);
        $headers = ['المؤشر', 'القيمة'];
        $rows = [
            ['نطاق العرض', $programme['scope'] === 'all' ? 'كل المنشآت' : 'المنشآت المسندة'],
            ['عدد المنشآت', (string) $programme['kpis']['enterprises_count']],
            ['استفسارات مفتوحة', (string) $programme['kpis']['open_escalations_count']],
            ['مواعيد امتثال متأخرة', (string) $programme['kpis']['overdue_compliance_count']],
        ];
        foreach ($programme['kpis']['referrals_by_status'] as $status) {
            $rows[] = ['إحالات — '.$status['label'], (string) $status['count']];
        }
        $rows[] = [];
        $rows[] = ['آخر الإحالات', ''];
        $rows[] = ['المعرف', 'المنشأة', 'الجهة', 'الحالة', 'آخر تحديث'];
        foreach ($programme['recent_referrals'] as $referral) {
            $rows[] = [
                $referral['public_id'],
                $referral['enterprise']['business_name'] ?? '',
                $referral['institution'] ?? '',
                $referral['status_label'],
                $referral['updated_at'] !== null ? substr($referral['updated_at'], 0, 10) : '',
            ];
        }

        return [$headers, $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{from: ?string, to: ?string, sector: ?string, region: ?string, gender: ?string}
     */
    public function normalizeFilters(array $filters): array
    {
        $gender = $filters['gender'] ?? null;
        if (! in_array($gender, ['female', 'male', 'mixed', 'unknown'], true)) {
            $gender = null;
        }

        return [
            'from' => filled($filters['from'] ?? null) ? (string) $filters['from'] : null,
            'to' => filled($filters['to'] ?? null) ? (string) $filters['to'] : null,
            'sector' => filled($filters['sector'] ?? null) ? (string) $filters['sector'] : null,
            'region' => filled($filters['region'] ?? null) ? (string) $filters['region'] : null,
            'gender' => $gender,
        ];
    }

    /**
     * @param  array{from: ?string, to: ?string, sector: ?string, region: ?string, gender: ?string}  $filters
     */
    private function enterpriseScope(User $user, array $filters): Builder
    {
        $query = $user->role === UserRole::BeaconAdmin
            ? Enterprise::query()
            : Enterprise::query()->whereHas('advisors', fn ($q) => $q->whereKey($user->id));

        if ($filters['sector'] !== null) {
            $query->where('sector', $filters['sector']);
        }
        if ($filters['region'] !== null) {
            $query->where('location', $filters['region']);
        }
        if ($filters['gender'] !== null) {
            $query->where(function (Builder $q) use ($filters): void {
                match ($filters['gender']) {
                    'female' => $q->where('female_employee_count', '>', 0)
                        ->where(fn (Builder $inner) => $inner->whereNull('male_employee_count')->orWhere('male_employee_count', 0)),
                    'male' => $q->where('male_employee_count', '>', 0)
                        ->where(fn (Builder $inner) => $inner->whereNull('female_employee_count')->orWhere('female_employee_count', 0)),
                    'mixed' => $q->where('female_employee_count', '>', 0)->where('male_employee_count', '>', 0),
                    'unknown' => $q->where(function (Builder $inner): void {
                        $inner->where(fn (Builder $x) => $x->whereNull('female_employee_count')->orWhere('female_employee_count', 0))
                            ->where(fn (Builder $x) => $x->whereNull('male_employee_count')->orWhere('male_employee_count', 0));
                    }),
                    default => null,
                };
            });
        }

        return $query;
    }
}
