export function formatMoney(
    cents: number,
    currency = 'EUR',
    locale = 'de',
): string {
    return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(
        cents / 100,
    );
}

export function formatRate(rate: number): string {
    return `${Number.isInteger(rate) ? rate : rate.toFixed(1)} %`;
}
