import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { expect, test } from 'vite-plus/test';
import { DisplaySettings } from '@/components/shop/display-settings';
import { setPage } from '@/test-utils';

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

test('accessibility tools change <html> and reset restores the defaults', async () => {
    const user = await openPanel();

    await user.click(screen.getByRole('radio', { name: '125 %' }));
    await user.click(screen.getByRole('checkbox', { name: 'Higher contrast' }));
    await user.click(screen.getByRole('checkbox', { name: 'Reduce motion' }));
    await user.click(screen.getByRole('checkbox', { name: 'Underline links' }));

    expect(
        Object.fromEntries(Object.entries(document.documentElement.dataset)),
    ).toEqual({
        a11yText: 'xl',
        a11yContrast: 'more',
        a11yMotion: 'reduce',
        a11yLinks: 'underline',
    });

    await user.click(screen.getByRole('checkbox', { name: 'Reduce motion' }));

    expect(document.documentElement.dataset.a11yMotion).toBeUndefined();

    await user.click(screen.getByRole('button', { name: 'Reset to defaults' }));

    expect(
        Object.fromEntries(Object.entries(document.documentElement.dataset)),
    ).toEqual({});
    expect(screen.getByRole('radio', { name: '100 %' })).toHaveProperty(
        'checked',
        true,
    );
});

test('a theme that is already at full contrast and has no motion offers neither switch', async () => {
    setPage({
        theme: { class: 'theme-x', high_contrast: true, no_motion: true },
    });

    await openPanel();

    expect(screen.getAllByRole('checkbox')).toHaveLength(1);
    expect(
        screen.getByRole('checkbox', { name: 'Underline links' }),
    ).toBeTruthy();
    expect(
        screen.queryByRole('checkbox', { name: 'Higher contrast' }),
    ).toBeNull();
    expect(
        screen.queryByRole('checkbox', { name: 'Reduce motion' }),
    ).toBeNull();
});
