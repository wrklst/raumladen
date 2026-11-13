import { expect, test } from 'vite-plus/test';
import { formatMoney, formatRate } from '@/lib/money';

test('formats cents as currency in the given locale', () => {
    expect(formatMoney(2390, 'EUR', 'de')).toBe('23,90 €');
    expect(formatMoney(2390, 'EUR', 'en')).toBe('€23.90');
});

test('formats whole and fractional VAT rates', () => {
    expect(formatRate(7)).toBe('7 %');
    expect(formatRate(5.5)).toBe('5.5 %');
});
