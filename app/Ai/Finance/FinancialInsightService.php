<?php

namespace App\Ai\Finance;

use App\Ai\Contracts\AiProvider;
use App\Ai\Support\AiDataGuard;
use App\Models\Enterprise;
use App\Services\FinancialReports\FinancialReportService;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * AI financial insights (rules §19). Figures come ONLY from the deterministic
 * FinancialReportService ("monthly" report); AI only explains them and never
 * calculates or overwrites balances. Only aggregate figures and the period are
 * sent to the provider — no transaction rows, names or user data.
 *
 * TODO: BUSINESS DECISION REQUIRED — reporting basis/periods/currency are still
 * open in §18; insights reuse the report's defaults and repeat its notes.
 */
class FinancialInsightService
{
    public static function aiLabel(): string
    {
        return ui('ai.assistant.suggestion_label');
    }

    public function __construct(
        private readonly AiProvider $provider,
        private readonly FinancialReportService $reports,
        private readonly TransactionReviewFlagService $flags,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function insights(Enterprise $enterprise, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $report = $this->reports->generate('monthly', $enterprise, $from, $to)->toArray();
        $flags = $this->flags->flags($enterprise, $from, $to);

        $insufficientRecords = ui('ai.insights.insufficient_records');
        $limitations = [];
        if ($flags !== []) {
            $limitations[] = ui('ai.insights.review_flags_limit');
        }
        if (collect($report['sections'])->every(fn ($s) => $s['rows'] === [])) {
            $limitations[] = $insufficientRecords;
        }

        $figures = [
            'period' => $report['period_label'],
            'report' => $report['title'],
            'sections' => array_map(fn ($s) => ['title' => $s['title'], 'columns' => $s['columns'], 'rows' => $s['rows']], $report['sections']),
            'notes' => $report['notes'],
            'limitations' => $limitations,
        ];

        $explanation = null;
        $aiAvailable = true;
        if (! in_array($insufficientRecords, $limitations, true)) {
            if (! AiDataGuard::allows(AiDataGuard::INSIGHTS)) {
                $aiAvailable = false;
            } else {
                try {
                    $explanation = mb_substr(strip_tags($this->provider->explainFigures($figures)), 0, 4000);
                } catch (Throwable $e) {
                    report($e);
                    $aiAvailable = false;
                }
            }
        }

        return [
            'period' => $report['period_label'],
            'figures' => $report,
            'limitations' => $limitations,
            'review_flags' => $flags,
            'ai_available' => $aiAvailable,
            'ai_generated' => $explanation !== null,
            'label' => $explanation !== null ? self::aiLabel() : null,
            'explanation' => $explanation,
            'message' => $aiAvailable ? null : 'الشرح الذكي غير متاح حالياً، والأرقام أعلاه صحيحة ومحسوبة من سجلاتك.',
        ];
    }
}
