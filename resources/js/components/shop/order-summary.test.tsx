import { render, screen } from '@testing-library/react';
import { expect, test } from 'vite-plus/test';
import { OrderSummary } from '@/components/shop/order-summary';
import type { Order } from '@/types';

const order = (overrides: Partial<Order>): Order =>
    ({
        currency: 'EUR',
        prices_include_tax: true,
        discount_cents: 0,
        coupon_code: null,
        subtotal_net_cents: 1869,
        shipping_net_cents: 364,
        shipping_tax_cents: 26,
        total_cents: 2390,
        tax_note: null,
        tax_summary: [
            { rate: 7, category: 'S', net: 2233, tax: 157, gross: 2390 },
        ],
        items: [
            {
                id: 1,
                name: 'Testbuch',
                sku: 'BK-1',
                quantity: 1,
                line_net_cents: 1869,
                line_gross_cents: 2000,
            },
        ],
        ...overrides,
    }) as Order;

test('a consumer order shows gross amounts with the VAT included', () => {
    render(<OrderSummary order={order({})} />);

    expect(screen.getByRole('table', { name: 'Ordered items' })).toBeTruthy();
    expect(screen.getByRole('cell', { name: /^20,00/ })).toBeTruthy();
    expect(screen.getByText('incl. 7 % VAT')).toBeTruthy();
    expect(screen.getByText(/^3,90/)).toBeTruthy();
    expect(screen.queryByText('Net total')).toBeNull();
});

test('a business order shows net amounts, VAT on top and the tax note', () => {
    render(
        <OrderSummary
            order={order({
                prices_include_tax: false,
                tax_note: 'Tax-free intra-community supply.',
                tax_summary: [
                    { rate: 0, category: 'K', net: 2233, tax: 0, gross: 2233 },
                ],
                total_cents: 2233,
            })}
        />,
    );

    expect(screen.getByRole('cell', { name: /^18,69/ })).toBeTruthy();
    expect(screen.getByText('Net total')).toBeTruthy();
    expect(screen.getByText('Tax-free intra-community supply.')).toBeTruthy();
    // Zero-rated groups are not listed as VAT lines.
    expect(screen.queryByText(/VAT$/)).toBeNull();
});
