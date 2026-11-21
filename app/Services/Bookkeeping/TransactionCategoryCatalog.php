<?php

namespace App\Services\Bookkeeping;

use App\Enums\TransactionType;
use App\Models\TransactionCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Single source for transaction categories used by forms, validation, AI suggestions
 * and display labels. Reads the admin-managed table; if the table has no rows at all
 * it falls back to config('bookkeeping.categories') so the app keeps working.
 *
 * Resolve once per request/loop: rows are memoised per instance.
 */
class TransactionCategoryCatalog
{
    /** @var Collection<int, array{key: string, label_ar: string, label_en: ?string, type_scope: ?string, is_active: bool}>|null */
    private ?Collection $rows = null;

    /**
     * Active categories for a transaction type as key => label (for forms and validation).
     * $alsoAllow keeps an already-saved (possibly deactivated) category selectable on corrections.
     *
     * @return array<string, string>
     */
    public function forType(string $type, ?string $alsoAllow = null): array
    {
        $out = [];
        foreach ($this->rows() as $row) {
            if (! $this->appliesTo($row, $type)) {
                continue;
            }
            if ($row['is_active'] || $row['key'] === $alsoAllow) {
                $out[$row['key']] = $this->labelOf($row);
            }
        }

        return $out;
    }

    /**
     * Every transaction type => active categories as key => label.
     *
     * @return array<string, array<string, string>>
     */
    public function grouped(?string $historicalType = null, ?string $historicalKey = null): array
    {
        $out = [];
        foreach (TransactionType::cases() as $type) {
            $out[$type->value] = $this->forType($type->value, $type->value === $historicalType ? $historicalKey : null);
        }

        return $out;
    }

    /**
     * Same shape as grouped(), as value/label pairs for select inputs.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    public function options(?string $historicalType = null, ?string $historicalKey = null): array
    {
        return collect($this->grouped($historicalType, $historicalKey))
            ->map(fn (array $items): array => collect($items)
                ->map(fn (string $label, string $key): array => ['value' => $key, 'label' => $label])
                ->values()
                ->all())
            ->all();
    }

    /**
     * Display label for saved records. Includes deactivated categories; unknown keys return the key.
     */
    public function label(string $type, string $key): string
    {
        foreach ($this->rows() as $row) {
            if ($row['key'] === $key && $this->appliesTo($row, $type)) {
                return $this->labelOf($row);
            }
        }

        return $key;
    }

    /**
     * All labels (including deactivated) for a type, for rendering lists of saved records.
     *
     * @return array<string, string>
     */
    public function labelsForType(string $type): array
    {
        $out = [];
        foreach ($this->rows() as $row) {
            if ($this->appliesTo($row, $type)) {
                $out[$row['key']] = $this->labelOf($row);
            }
        }

        return $out;
    }

    /**
     * @param  array{type_scope: ?string}  $row
     */
    private function appliesTo(array $row, string $type): bool
    {
        return $row['type_scope'] === null || $row['type_scope'] === $type;
    }

    /**
     * @param  array{label_ar: string, label_en: ?string}  $row
     */
    private function labelOf(array $row): string
    {
        if (app()->getLocale() === 'en' && filled($row['label_en'])) {
            return $row['label_en'];
        }

        return $row['label_ar'];
    }

    /**
     * @return Collection<int, array{key: string, label_ar: string, label_en: ?string, type_scope: ?string, is_active: bool}>
     */
    private function rows(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        // Before the migration has run (or in a bare unit test) there is no table: use the config defaults.
        $rows = Schema::hasTable('transaction_categories')
            ? TransactionCategory::query()->orderBy('sort')->orderBy('id')->get()
                ->map(fn (TransactionCategory $c): array => [
                    'key' => $c->key,
                    'label_ar' => $c->label_ar,
                    'label_en' => $c->label_en,
                    'type_scope' => $c->type_scope,
                    'is_active' => $c->is_active,
                ])
            : collect();

        if ($rows->isEmpty()) {
            $rows = $this->configRows();
        }

        return $this->rows = $rows->values();
    }

    /**
     * @return Collection<int, array{key: string, label_ar: string, label_en: ?string, type_scope: ?string, is_active: bool}>
     */
    private function configRows(): Collection
    {
        $rows = collect();
        foreach ((array) config('bookkeeping.categories', []) as $type => $items) {
            foreach ((array) $items as $key => $label) {
                $rows->push([
                    'key' => (string) $key,
                    'label_ar' => (string) $label,
                    'label_en' => null,
                    'type_scope' => (string) $type,
                    'is_active' => true,
                ]);
            }
        }

        return $rows;
    }
}
