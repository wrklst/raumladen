import { useSyncExternalStore } from 'react';

/**
 * Visitor display preferences. Stored in the shape of <html>'s dataset
 * (data-a11y-text, data-a11y-contrast, ...) so the inline script in
 * app.blade.php can apply them before first paint with one Object.assign;
 * app.css holds the rules.
 */
export type A11yPrefs = {
    a11yText?: 'lg' | 'xl' | '2xl';
    a11yContrast?: 'more';
    a11yMotion?: 'reduce';
    a11yLinks?: 'underline';
};

const STORAGE_KEY = 'a11y';
const KEYS = ['a11yText', 'a11yContrast', 'a11yMotion', 'a11yLinks'] as const;
const NONE: A11yPrefs = {};
const listeners = new Set<() => void>();

const read = (): A11yPrefs => {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}');
    } catch {
        return NONE;
    }
};

let current: A11yPrefs = typeof window === 'undefined' ? NONE : read();

const commit = (next: A11yPrefs): void => {
    current = next;

    for (const key of KEYS) {
        delete document.documentElement.dataset[key];
    }

    Object.assign(document.documentElement.dataset, next);

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
    } catch {
        // Storage blocked: the preference still applies to this page view.
    }

    listeners.forEach((listener) => listener());
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

export function useA11yPrefs() {
    const prefs = useSyncExternalStore(
        subscribe,
        () => current,
        () => NONE,
    );

    const setPref = <K extends keyof A11yPrefs>(
        key: K,
        value: A11yPrefs[K] | undefined,
    ): void => {
        const next = { ...current };

        if (value === undefined) {
            delete next[key];
        } else {
            next[key] = value;
        }

        commit(next);
    };

    return { prefs, setPref, resetPrefs: () => commit({}) } as const;
}
