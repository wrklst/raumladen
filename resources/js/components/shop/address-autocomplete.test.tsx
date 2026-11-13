import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { expect, test, vi } from 'vite-plus/test';
import { AddressFields } from '@/components/shop/address-fields';

const suggestion = {
    address1: 'Hauptstraße 157',
    zip: '51465',
    city: 'Bergisch Gladbach',
    country: 'DE',
    label: 'Hauptstraße 157, 51465 Bergisch Gladbach',
};

// The country <select> has options too: count only the suggestion list's.
const suggestionCount = () =>
    within(screen.getByRole('listbox')).queryAllByRole('option').length;

function mockSuggestions(suggestions: unknown[]) {
    return vi.spyOn(globalThis, 'fetch').mockResolvedValue({
        ok: true,
        json: async () => ({
            suggestions,
            attribution: '© OpenStreetMap contributors',
        }),
    } as Response);
}

function renderFields() {
    render(
        <AddressFields
            prefix="billing_address"
            values={{ country: 'DE' }}
            countries={{ DE: 'Deutschland', AT: 'Österreich' }}
            errors={{}}
            required
        />,
    );

    return screen.getByRole('combobox', { name: 'Street and number' });
}

test('picking a suggestion with the keyboard fills street, postcode and city', async () => {
    const fetchMock = mockSuggestions([suggestion]);
    const user = userEvent.setup();
    const street = renderFields();

    await user.type(street, 'Hauptstr');

    const option = await screen.findByRole('option', {
        name: suggestion.label,
    });

    expect(street.getAttribute('aria-expanded')).toBe('true');
    // The source is named next to the data (ODbL).
    expect(screen.getByText('© OpenStreetMap contributors')).toBeTruthy();
    // Debounced: eight keystrokes, one request, for the selected country.
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock.mock.calls[0][0] as string).toContain('q=Hauptstr');
    expect(fetchMock.mock.calls[0][0] as string).toContain('country=DE');

    await user.keyboard('{ArrowDown}');

    expect(street.getAttribute('aria-activedescendant')).toBe(option.id);

    await user.keyboard('{Enter}');

    expect(street).toHaveProperty('value', 'Hauptstraße 157');
    expect(screen.getByRole('textbox', { name: 'Postal code' })).toHaveProperty(
        'value',
        '51465',
    );
    expect(screen.getByRole('textbox', { name: 'City' })).toHaveProperty(
        'value',
        'Bergisch Gladbach',
    );
    expect(suggestionCount()).toBe(0);
    expect(street.getAttribute('aria-expanded')).toBe('false');
});

test('escape closes the list and short input asks nothing', async () => {
    const fetchMock = mockSuggestions([suggestion]);
    const user = userEvent.setup();
    const street = renderFields();

    await user.type(street, 'Ha');
    await new Promise((resolve) => setTimeout(resolve, 350));

    expect(fetchMock).not.toHaveBeenCalled();

    await user.type(street, 'uptstr');
    await screen.findByRole('option', { name: suggestion.label });
    await user.keyboard('{Escape}');

    expect(suggestionCount()).toBe(0);
    expect(street).toHaveProperty('value', 'Hauptstr');
});

test('the field stays a plain input when the service fails', async () => {
    vi.spyOn(globalThis, 'fetch').mockRejectedValue(new Error('offline'));
    const user = userEvent.setup();
    const street = renderFields();

    await user.type(street, 'Hauptstraße 1');
    await waitFor(() => expect(fetch).toHaveBeenCalled());

    expect(street).toHaveProperty('value', 'Hauptstraße 1');
    expect(suggestionCount()).toBe(0);
});
