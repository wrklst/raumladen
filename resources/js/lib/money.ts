export function formatMoney(
    cents: number,
    currency = 'EUR',
    locale = 'de',
): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
    }).format(cents / 100);
}

export function formatRate(rate: number): string {
    return `${Number.isInteger(rate) ? rate : rate.toFixed(1)} %`;
}

/**
 * What stands beside a price: with VAT, net, or nothing about VAT at all. A
 * product without a rate is sold by a small business (§ 19 UStG), which says
 * so once, in its terms and on the shipping page (`:vat_note`).
 */
export function priceNote(
    product: { prices_include_tax: boolean; tax_rate: number | null },
    t: (key: string, params?: Record<string, string | number>) => string,
): string {
    if (product.tax_rate === null) {
        return t('plus shipping');
    }

    return product.prices_include_tax
        ? t('incl. :rate VAT, plus shipping', {
              rate: formatRate(product.tax_rate),
          })
        : t('net, plus VAT and shipping');
}
