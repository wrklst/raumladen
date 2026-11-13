import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { expect, test } from 'vite-plus/test';
import Checkout from '@/pages/shop/checkout';
import { setPage } from '@/test-utils';
import type { CartTotals } from '@/types';

const cart = {
    lines: [
        {
            product_id: 1,
            name: 'Testbuch',
            quantity: 2,
            line_display_cents: 4000,
        },
    ],
    prices_include_tax: true,
    country: 'DE',
    vat_id_valid: false,
    subtotal_display_cents: 4000,
    discount_cents: 0,
    coupon_code: null,
    shipping_display_cents: 390,
    total_cents: 4390,
    tax_summary: [{ rate: 7, category: 'S', net: 4103, tax: 287, gross: 4390 }],
    tax_note: null,
} as unknown as CartTotals;

const props = {
    cart,
    countries: { DE: 'Deutschland', AT: 'Österreich' },
    gateways: ['mollie', 'bank_transfer'],
    defaults: {
        email: null,
        billing_address: null,
        shipping_address: null,
        vat_id: null,
        company: null,
    },
    legal_pages: [{ slug: 'terms', title: 'AGB' }],
};

test('address and payment are grouped and labelled; ordering needs no extra tick', () => {
    render(<Checkout {...props} />);

    const billing = screen.getByRole('group', { name: 'Billing address' });

    expect(
        within(billing).getByRole('textbox', { name: 'First name' }),
    ).toHaveProperty('required', true);
    expect(
        within(billing)
            .getByRole('textbox', { name: 'First name' })
            .getAttribute('autocomplete'),
    ).toBe('billing given-name');
    expect(
        within(billing).getByRole('combobox', { name: 'Country' }),
    ).toHaveProperty('value', 'DE');

    const payment = screen.getByRole('group', { name: 'Payment' });

    expect(within(payment).getAllByRole('radio')).toHaveLength(2);
    expect(within(payment).getAllByRole('radio')[0]).toHaveProperty(
        'checked',
        true,
    );

    expect(
        screen.getByRole('link', { name: /^AGB\s*\(opens in a new tab\)$/ }),
    ).toBeTruthy();
    // Only the optional "ship elsewhere" tick remains: no terms checkbox to click.
    expect(screen.getAllByRole('checkbox')).toHaveLength(1);
    // The country narrows the street suggestions, so it comes before the street.
    const country = within(billing).getByRole('combobox', { name: 'Country' });
    const street = within(billing).getByRole('combobox', {
        name: 'Street and number',
    });

    expect(
        country.compareDocumentPosition(street) &
            Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy();
    expect(
        screen.getByRole('button', { name: 'Place binding order' }),
    ).toBeTruthy();
    // German law wants the total right above the order button.
    expect(screen.getByText(/^43,90/)).toBeTruthy();
});

test('the shipping address appears only when shipping elsewhere', async () => {
    const user = userEvent.setup();

    render(<Checkout {...props} />);

    expect(
        screen.queryByRole('group', { name: 'Shipping address' }),
    ).toBeNull();

    await user.click(
        screen.getByRole('checkbox', { name: 'Ship to a different address' }),
    );

    const shipping = screen.getByRole('group', { name: 'Shipping address' });

    expect(
        within(shipping)
            .getByRole('textbox', { name: 'Postal code' })
            .getAttribute('autocomplete'),
    ).toBe('shipping postal-code');
});

test('server validation errors are attached to their fields', () => {
    setPage({
        errors: { email: 'Enter an email address.', vat_id: 'Not confirmed.' },
    });

    render(<Checkout {...props} />);

    const email = screen.getByRole('textbox', { name: 'Email address' });

    expect(email.getAttribute('aria-invalid')).toBe('true');
    expect(
        document.getElementById(email.getAttribute('aria-describedby') ?? '')
            ?.textContent,
    ).toBe('Enter an email address.');
    // A hidden field with an error must not stay hidden.
    expect(
        screen
            .getByRole('textbox', { name: 'VAT ID (businesses in the EU)' })
            .closest('details'),
    ).toHaveProperty('open', true);
});
