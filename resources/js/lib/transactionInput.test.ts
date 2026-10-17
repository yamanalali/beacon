import { describe, expect, it } from 'vitest';
import { exactTransactionMoney, normalizeTransactionAmount, transactionMinor, transactionToday } from './transactionInput';
describe('localized transaction input', () => {
    it('normalizes Arabic, Persian and properly grouped Latin amounts', () => {
        for (const input of ['١٬٢٣٤٫٥٠', '۱٬۲۳۴٫۵۰', '1,234.50']) expect(normalizeTransactionAmount(input)).toBe('1234.50');
        expect(transactionMinor('١٬٢٣٤٫٥٠')).toBe(123450n);
    });
    it('does not guess ambiguous commas, malformed grouping, signs or excess decimals', () => {
        for (const input of ['1,5', '12,34', '1 000', '-1', '1.234', '1e3', 'abc']) expect(transactionMinor(input)).toBeNull();
    });
    it('preserves exact cents for large amounts and uses the browser local day', () => {
        expect(exactTransactionMoney(transactionMinor('9999999999999999.99')! - transactionMinor('0.01')!, 'USD')).toBe('9,999,999,999,999,999.98 USD');
        expect(transactionToday(new Date(2026, 9, 17, 23, 59))).toBe('2026-10-17');
    });
});
