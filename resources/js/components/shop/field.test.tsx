import { render, screen } from '@testing-library/react';
import { expect, test } from 'vite-plus/test';
import { Field } from '@/components/shop/field';

test('label, required state and error are exposed to assistive technology', () => {
    render(
        <Field label="Email address" name="email" error="Required." required />,
    );

    // The visual asterisk must not leak into the accessible name.
    const input = screen.getByRole('textbox', { name: 'Email address' });

    expect(input).toHaveProperty('required', true);
    expect(input.getAttribute('aria-invalid')).toBe('true');
    expect(screen.getByRole('alert').textContent).toBe('Required.');
    expect(input.getAttribute('aria-describedby')).toBe(
        screen.getByRole('alert').id,
    );
});

test('a valid field carries no error attributes', () => {
    render(<Field label="City" name="city" defaultValue="Berlin" />);

    const input = screen.getByRole('textbox', { name: 'City' });

    expect(input).toHaveProperty('value', 'Berlin');
    expect(input.hasAttribute('aria-invalid')).toBe(false);
    expect(input.hasAttribute('aria-describedby')).toBe(false);
    expect(screen.queryByRole('alert')).toBeNull();
});
