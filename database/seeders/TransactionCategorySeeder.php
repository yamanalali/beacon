<?php

namespace Database\Seeders;

use App\Models\TransactionCategory;
use Illuminate\Database\Seeder;

/**
 * Copies the placeholder categories from config/bookkeeping.php into the admin-managed table.
 * Safe to re-run: existing rows (and any admin edits) are never overwritten.
 */
class TransactionCategorySeeder extends Seeder
{
    /** @var array<string, string> */
    private const ENGLISH = [
        'general_sales' => 'General sales',
        'other_income' => 'Other income',
        'goods' => 'Goods and materials',
        'rent' => 'Rent',
        'utilities' => 'Electricity, water and communications',
        'salaries' => 'Wages and salaries',
        'transport' => 'Transport',
        'meals' => 'Food and drinks',
        'other_expense' => 'Other expenses',
    ];

    public function run(): void
    {
        $sort = 0;
        foreach ((array) config('bookkeeping.categories', []) as $type => $items) {
            foreach ((array) $items as $key => $label) {
                $sort += 10;
                TransactionCategory::query()->firstOrCreate(
                    ['key' => (string) $key],
                    [
                        'label_ar' => (string) $label,
                        'label_en' => self::ENGLISH[$key] ?? null,
                        'type_scope' => (string) $type,
                        'is_active' => true,
                        'sort' => $sort,
                    ],
                );
            }
        }
    }
}
