<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact money arithmetic using integer minor units (no floats).
 * Amounts are stored in the database as DECIMAL(18,2).
 */
final class Money
{
    public static function toMinor(string|int $amount): int
    {
        $amount = trim((string) $amount);

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return ((int) $matches[1]) * 100 + (int) $fraction;
    }

    public static function fromMinor(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return $sign.intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
