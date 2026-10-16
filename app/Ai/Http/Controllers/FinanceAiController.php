<?php

namespace App\Ai\Http\Controllers;

use App\Ai\Finance\FinancialInsightService;
use App\Ai\Finance\TransactionReviewFlagService;
use App\Http\Controllers\Controller;
use App\Models\Enterprise;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Read-only AI/analytics over finance data; same access as financial reports. */
class FinanceAiController extends Controller
{
    public function flags(Request $request, Enterprise $enterprise, TransactionReviewFlagService $service): JsonResponse
    {
        Gate::authorize('reports.view', $enterprise);
        [$from, $to] = $this->period($request);

        return response()->json(['flags' => $service->flags($enterprise, $from, $to)]);
    }

    public function insights(Request $request, Enterprise $enterprise, FinancialInsightService $service): JsonResponse
    {
        Gate::authorize('reports.view', $enterprise);
        [$from, $to] = $this->period($request);

        return response()->json($service->insights($enterprise, $from, $to));
    }

    /** @return array{CarbonImmutable,CarbonImmutable} */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to']) : CarbonImmutable::today();
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from']) : $to->startOfMonth();
        abort_if($from->diffInDays($to) > 366, 422, 'الفترة طويلة جداً.');

        return [$from, $to];
    }
}
