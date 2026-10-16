<?php

/*
|--------------------------------------------------------------------------
| Bookkeeping configuration
|--------------------------------------------------------------------------
|
| TODO: BUSINESS DECISION REQUIRED
| - Supported currencies, base/reporting currency, conversion-rate source,
|   approval process and rounding rules are NOT yet confirmed by Beacon.
|   Until then each record keeps its original amount and currency only; no
|   conversion is performed and totals are always shown per currency.
| - The category list below is a placeholder structure pending Beacon input.
|   It can be replaced here without touching application code.
|
*/

return [
    'currencies' => [
        'SYP' => 'ليرة سورية',
        'USD' => 'دولار أمريكي',
        // Recognised from receipts only; never converted (manager decision).
        'AED' => 'درهم إماراتي',
    ],

    'default_currency' => 'SYP',

    // Amounts are stored as DECIMAL(18,2). Rounding rules pending Beacon.
    'decimals' => 2,

    'categories' => [
        'sale' => [
            'general_sales' => 'مبيعات عامة',
        ],
        'income' => [
            'other_income' => 'دخل آخر',
        ],
        'purchase' => [
            'goods' => 'بضاعة ومواد',
        ],
        'expense' => [
            'rent' => 'إيجار',
            'utilities' => 'كهرباء وماء واتصالات',
            'salaries' => 'أجور ورواتب',
            'transport' => 'نقل ومواصلات',
            'other_expense' => 'مصاريف أخرى',
        ],
    ],
];
