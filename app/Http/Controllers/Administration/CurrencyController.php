<?php

namespace App\Http\Controllers\Administration;

use App\Actions\Audit\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\FinancialTransaction;
use App\Support\Currencies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CurrencyController extends Controller
{
    public function index(Currencies $catalog)
    {
        return Inertia::render('Administration/Currencies', ['currencies' => Currency::query()->orderBy('code')->get()->map(fn ($c) => $c->toArray() + ['used' => FinancialTransaction::query()->where('currency', $c->code)->exists()]), 'defaultCurrency' => $catalog->default()]);
    }

    public function store(Request $request, RecordAuditLog $audit)
    {
        $data = $request->validate(['code' => ['required', 'regex:/^[A-Z]{3}$/', Rule::unique('currencies', 'code')], 'name' => ['required', 'string', 'max:255']]);
        $currency = Currency::query()->create($data + ['enabled' => true]);
        $audit->record($request->user(), 'currency.created', $currency, $data);

        return back()->with('success', 'تمت إضافة العملة.');
    }

    public function update(Request $request, Currency $currency, Currencies $catalog, RecordAuditLog $audit)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'enabled' => ['required', 'boolean'], 'is_default' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $currency, $catalog, $audit, $data) {
            Currency::query()->orderBy('code')->lockForUpdate()->get();
            if (! $data['enabled'] && ($currency->code === $catalog->default() || $data['is_default'])) {
                throw ValidationException::withMessages(['enabled' => 'اختر عملة افتراضية أخرى قبل إيقاف هذه العملة.']);
            }
            $before = $currency->fresh()->only(['name', 'enabled', 'is_default']);
            if ($data['is_default']) {
                Currency::query()->update(['is_default' => false]);
            }
            $currency->update($data);
            $audit->record($request->user(), 'currency.updated', $currency, ['before' => $before, 'after' => $data]);
        });

        return back()->with('success', 'تم تحديث العملة. تبقى السجلات السابقة محفوظة.');
    }
}
