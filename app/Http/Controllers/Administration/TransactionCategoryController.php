<?php

namespace App\Http\Controllers\Administration;

use App\Actions\Bookkeeping\DeactivateTransactionCategoryAction;
use App\Actions\Bookkeeping\SaveTransactionCategoryAction;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\SaveTransactionCategoryRequest;
use App\Models\FinancialTransaction;
use App\Models\TransactionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TransactionCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage', TransactionCategory::class);

        $usedKeys = FinancialTransaction::query()->distinct()->pluck('category')->all();

        return Inertia::render('Administration/Categories', [
            'categories' => TransactionCategory::query()->orderBy('sort')->orderBy('id')->get()
                ->map(fn (TransactionCategory $c): array => [
                    'id' => $c->id,
                    'key' => $c->key,
                    'label_ar' => $c->label_ar,
                    'label_en' => $c->label_en,
                    'type_scope' => $c->type_scope,
                    'is_active' => $c->is_active,
                    'sort' => $c->sort,
                    'used' => in_array($c->key, $usedKeys, true),
                ])->values(),
            'types' => collect(TransactionType::cases())->map(fn (TransactionType $t): array => ['value' => $t->value, 'label' => $t->label()])->all(),
        ]);
    }

    public function store(SaveTransactionCategoryRequest $request, SaveTransactionCategoryAction $action): RedirectResponse
    {
        $action->execute($request->user(), null, $request->validated());

        return back()->with('success', ui('flash.category_saved'));
    }

    public function update(SaveTransactionCategoryRequest $request, TransactionCategory $category, SaveTransactionCategoryAction $action): RedirectResponse
    {
        $action->execute($request->user(), $category, $request->validated());

        return back()->with('success', ui('flash.category_updated'));
    }

    public function deactivate(Request $request, TransactionCategory $category, DeactivateTransactionCategoryAction $action): RedirectResponse
    {
        Gate::authorize('manage', TransactionCategory::class);

        $action->execute($request->user(), $category);

        return back()->with('success', ui('flash.category_deactivated'));
    }
}
