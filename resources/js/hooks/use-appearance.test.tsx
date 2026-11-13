import { act, renderHook } from '@testing-library/react';
import { expect, test } from 'vite-plus/test';
import { initializeTheme, useAppearance } from '@/hooks/use-appearance';

test('choosing dark sets the class, colour scheme, cookie and storage', () => {
    const { result } = renderHook(() => useAppearance());

    act(() => result.current.updateAppearance('dark'));

    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.style.colorScheme).toBe('dark');
    expect(localStorage.getItem('appearance')).toBe('dark');
    // The cookie lets the server render the right class (no flash with SSR).
    expect(document.cookie).toContain('appearance=dark');
    expect(result.current.resolvedAppearance).toBe('dark');

    act(() => result.current.updateAppearance('light'));

    expect(document.documentElement.classList.contains('dark')).toBe(false);
});

test('system follows the operating system', () => {
    window.matchMedia = ((query: string) => ({
        matches: query.includes('dark'),
        addEventListener: () => {},
    })) as unknown as typeof window.matchMedia;

    const { result } = renderHook(() => useAppearance());

    act(() => result.current.updateAppearance('system'));

    expect(document.documentElement.classList.contains('dark')).toBe(true);
});

test('a first visit stores nothing until the visitor chooses', () => {
    initializeTheme();

    expect(localStorage.getItem('appearance')).toBeNull();
    expect(document.cookie).not.toContain('appearance');
});
