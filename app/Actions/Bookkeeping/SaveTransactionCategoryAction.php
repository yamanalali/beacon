<?php

namespace App\Actions\Bookkeeping;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\TransactionType;
use App\Models\TransactionCategory;
use App\Models\User;
use Database\Seeders\TransactionCategorySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a transaction category. The key is fixed after creation because
 * saved transactions reference it. A change that would leave a transaction type
 * with no active category is rejected (users could no longer record that type).
 */
class SaveTransactionCategoryAction
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /**
     * @param  array<string, mixed>  $data  validated: key (create only), label_ar, label_en, type_scope, is_active, sort
     */
    public function execute(User $actor, ?TransactionCategory $category, array $data): TransactionCategory
    {
        abort_unless($actor->can('manage', TransactionCategory::class), 403);

        return DB::transaction(function () use ($actor, $category, $data): TransactionCategory {
            $creating = $category === null;
            // Serialise concurrent edits so the "at least one active per type" check holds.
            $existing = TransactionCategory::query()->lockForUpdate()->pluck('id');
            if ($existing->isEmpty()) {
                // The table is about to become the source of truth, so keep the default categories.
                (new TransactionCategorySeeder)->run();
            }

            $category = $creating ? new TransactionCategory : $category->fresh();
            $before = $creating ? null : $category->only(['label_ar', 'label_en', 'type_scope', 'is_active', 'sort']);

            if ($creating) {
                $category->key = $data['key'];
            }
            $category->label_ar = $data['label_ar'];
            $category->label_en = filled($data['label_en'] ?? null) ? $data['label_en'] : null;
            $category->type_scope = filled($data['type_scope'] ?? null) ? $data['type_scope'] : null;
            $category->is_active = (bool) ($data['is_active'] ?? true);
            $category->sort = (int) ($data['sort'] ?? 0);
            $category->save();

            if (! $category->is_active || ($before !== null && $before['type_scope'] !== $category->type_scope)) {
                self::assertEveryTypeHasActiveCategory();
            }

            $after = $category->only(['label_ar', 'label_en', 'type_scope', 'is_active', 'sort']);
            $this->audit->record($actor, $creating ? 'transaction_category.created' : 'transaction_category.updated', $category, [
                'key' => $category->key,
                'before' => $before,
                'after' => $after,
            ]);

            return $category;
        });
    }

    /**
     * @throws ValidationException
     */
    public static function assertEveryTypeHasActiveCategory(): void
    {
        foreach (TransactionType::cases() as $type) {
            $hasActive = TransactionCategory::query()->active()
                ->where(fn ($q) => $q->whereNull('type_scope')->orWhere('type_scope', $type->value))
                ->exists();
            if (! $hasActive) {
                throw ValidationException::withMessages([
                    'is_active' => ui('validation.category_last_active', ['type' => $type->label()]),
                ]);
            }
        }
    }
}
