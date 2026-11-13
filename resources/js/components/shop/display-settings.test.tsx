import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { expect, test } from 'vite-plus/test';
import { DisplaySettings } from '@/components/shop/display-settings';

async function openPanel() {
    const user = userEvent.setup();

    render(<DisplaySettings />);
    await user.click(screen.getByRole('button', { name: 'Display' }));

    return user;
}

test('the panel is a labelled dialog with light, dark and system', async () => {
    const user = await openPanel();

    expect(
        screen.getByRole('dialog', { name: 'Display and accessibility' }),
    ).toBeTruthy();
    expect(screen.getByRole('radio', { name: 'System' })).toHaveProperty(
        'checked',
        true,
    );

    await user.click(screen.getByRole('radio', { name: 'Dark' }));

    expect(document.documentElement.classList.contains('dark')).toBe(true);

    await user.click(screen.getByRole('radio', { name: 'Light' }));

    expect(document.documentElement.classList.contains('dark')).toBe(false);
});

test('only options that change something in this theme are offered', async () => {
    const user = await openPanel();

    // Black on white without motion: no contrast switch, no motion switch.
    expect(screen.getAllByRole('checkbox')).toHaveLength(1);
    expect(screen.queryByRole('checkbox', { name: /contrast/i })).toBeNull();
    expect(screen.queryByRole('checkbox', { name: /motion/i })).toBeNull();

    await user.click(screen.getByRole('radio', { name: '125 %' }));
    await user.click(screen.getByRole('checkbox', { name: 'Underline links' }));

    expect(
        Object.fromEntries(Object.entries(document.documentElement.dataset)),
    ).toEqual({ a11yText: 'xl', a11yLinks: 'underline' });

    await user.click(screen.getByRole('checkbox', { name: 'Underline links' }));

    expect(document.documentElement.dataset.a11yLinks).toBeUndefined();

    await user.click(screen.getByRole('button', { name: 'Reset to defaults' }));

    expect(
        Object.fromEntries(Object.entries(document.documentElement.dataset)),
    ).toEqual({});
    expect(screen.getByRole('radio', { name: '100 %' })).toHaveProperty(
        'checked',
        true,
    );
});
