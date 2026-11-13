import { act, renderHook } from '@testing-library/react';
import { afterEach, expect, test } from 'vite-plus/test';
import { useA11yPrefs } from '@/hooks/use-a11y-prefs';

// The store is module state, like in the browser: reset it between tests.
afterEach(() => {
    const { result } = renderHook(() => useA11yPrefs());

    act(() => result.current.resetPrefs());
});

test('a preference is applied to <html> and saved for the next visit', () => {
    const { result } = renderHook(() => useA11yPrefs());

    act(() => result.current.setPref('a11yText', 'xl'));
    act(() => result.current.setPref('a11yLinks', 'underline'));

    expect(document.documentElement.dataset.a11yText).toBe('xl');
    expect(document.documentElement.dataset.a11yLinks).toBe('underline');
    expect(result.current.prefs).toEqual({
        a11yText: 'xl',
        a11yLinks: 'underline',
    });
    // Same shape the inline script in app.blade.php assigns to the dataset.
    expect(JSON.parse(localStorage.getItem('a11y') ?? '')).toEqual({
        a11yText: 'xl',
        a11yLinks: 'underline',
    });
});

test('clearing one preference keeps the other', () => {
    const { result } = renderHook(() => useA11yPrefs());

    act(() => result.current.setPref('a11yText', 'lg'));
    act(() => result.current.setPref('a11yLinks', 'underline'));
    act(() => result.current.setPref('a11yText', undefined));

    expect(document.documentElement.dataset.a11yText).toBeUndefined();
    expect(document.documentElement.dataset.a11yLinks).toBe('underline');
});

test('reset removes every preference', () => {
    const { result } = renderHook(() => useA11yPrefs());

    act(() => result.current.setPref('a11yText', '2xl'));
    act(() => result.current.resetPrefs());

    expect(document.documentElement.dataset.a11yText).toBeUndefined();
    expect(localStorage.getItem('a11y')).toBe('{}');
    expect(result.current.prefs).toEqual({});
});
