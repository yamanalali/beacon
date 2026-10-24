<?php

namespace App\Http\Controllers\Offline;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Support\OfflinePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class OfflineController extends Controller
{
    public function index()
    {
        return Inertia::render('Offline/Index');
    }

    public function session(Request $request)
    {
        return response()->json(['scope' => OfflinePolicy::scope($request->user()), 'csrf' => csrf_token(), 'version' => app(HandleInertiaRequests::class)->version($request)])
            ->header('Cache-Control', 'no-store');
    }

    public function reportData(Request $request, Enterprise $enterprise)
    {
        Gate::authorize('reports.view', $enterprise);
        $transactions = FinancialTransaction::query()->posted()->where('enterprise_id', $enterprise->id)
            ->with('settlements')->orderBy('id')->limit(20001)->get();
        $complete = $transactions->count() <= 20000;

        return response()->json([
            'complete' => $complete,
            'saved_at' => now()->toIso8601String(),
            'enterprise' => ['public_id' => $enterprise->public_id, 'business_name' => $enterprise->business_name],
            'types' => collect(TransactionType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()]),
            'categories' => config('bookkeeping.categories'),
            'transactions' => $complete ? $transactions->map(fn ($t) => [
                'status' => $t->status->value, 'type' => $t->type->value, 'category' => $t->category,
                'amount' => $t->amount, 'currency' => $t->currency, 'date' => $t->transaction_date->toDateString(),
                'counterparty' => $t->counterparty,
                'settlements' => $t->settlements->map(fn ($s) => ['date' => $s->settlement_date->toDateString(), 'amount' => $s->amount]),
            ]) : [],
        ])->header('Cache-Control', 'no-store');
    }

    public function assets()
    {
        $path = public_path('build/manifest.json');
        abort_unless(is_file($path), 503);
        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $assets = ['/offline.html', '/offline-core.js', '/offline-reports.js', '/manifest.webmanifest', '/icons/icon-192.png', '/icons/icon-512.png'];
        foreach ($manifest as $entry) {
            foreach ([$entry['file'], ...($entry['css'] ?? []), ...($entry['assets'] ?? [])] as $file) {
                $assets[] = '/build/'.$file;
            }
        }
        // Self-hosted fonts are referenced by app.css, outside Vite's manifest.
        foreach (glob(public_path('fonts/*/*')) ?: [] as $font) {
            if (is_file($font)) {
                $assets[] = '/'.substr($font, strlen(public_path()) + 1);
            }
        }

        return response()->json(['version' => hash_file('sha256', $path), 'assets' => array_values(array_unique($assets))])->header('Cache-Control', 'no-store');
    }
}
