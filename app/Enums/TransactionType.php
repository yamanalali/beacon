<?php

namespace App\Enums;

enum TransactionType: string
{
    case Sale = 'sale';
    case Income = 'income';
    case Purchase = 'purchase';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'بيع',
            self::Income => 'دخل آخر',
            self::Purchase => 'شراء',
            self::Expense => 'مصروف',
        };
    }

    /**
     * Money coming in to the enterprise (sales, income) vs going out.
     */
    public function isInflow(): bool
    {
        return in_array($this, [self::Sale, self::Income], true);
    }
}
