<?php

namespace App\Actions\Bookkeeping;

use App\Actions\Audit\RecordAuditLog;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Stops a category being offered for new transactions. Saved transactions keep
 * their category and label; nothing is deleted.
 */
class DeactivateTransactionCategoryAction
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(User $actor, TransactionCategory $category): void
    {
        abort_unless($actor->can('manage', TransactionCategory::class), 403);

        DB::transaction(function () use ($actor, $category): void {
            TransactionCategory::query()->lockForUpdate()->pluck('id');
            $current = TransactionCategory::query()->findOrFail($category->id);
            abort_unless($current->is_active, 409);

            $current->is_active = false;
            $current->save();

            SaveTransactionCategoryAction::assertEveryTypeHasActiveCategory();

            $this->audit->record($actor, 'transaction_category.deactivated', $current, [
                'key' => $current->key,
            ]);
        });
    }
}
