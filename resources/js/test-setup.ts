import { cleanup } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, vi } from 'vite-plus/test';
import { currentPage, setPage } from '@/test-utils';

type AnyProps = Record<string, unknown> & { children?: unknown };

// Components are tested without a router: links become anchors, forms render
// their children with the page's validation errors.
vi.mock('@inertiajs/react', async () => {
    const { createElement } = await import('react');

    return {
        usePage: () => currentPage(),
        Head: () => null,
        Link: ({ href, children, ...rest }: AnyProps) =>
            createElement(
                'a',
                {
                    href:
                        typeof href === 'string'
                            ? href
                            : (href as { url: string }).url,
                    ...rest,
                },
                children as ReactNode,
            ),
        Form: ({ children, ...rest }: AnyProps) =>
            createElement(
                'form',
                rest,
                typeof children === 'function'
                    ? children({
                          processing: false,
                          errors: currentPage().props.errors ?? {},
                      })
                    : (children as ReactNode),
            ),
        router: {
            reload: vi.fn(),
            get: vi.fn(),
            push: vi.fn(),
            replace: vi.fn(),
            on: () => () => {},
        },
    };
});

if (typeof window !== 'undefined') {
    // Radix measures its checkbox with a ResizeObserver, which jsdom lacks.
    window.ResizeObserver ??= class {
        observe() {}
        unobserve() {}
        disconnect() {}
    };

    // jsdom has no matchMedia; the appearance hook asks for the system scheme.
    window.matchMedia ??= (query: string) =>
        ({
            matches: false,
            media: query,
            addEventListener: () => {},
            removeEventListener: () => {},
        }) as unknown as MediaQueryList;
}

beforeEach(() => setPage());

afterEach(() => {
    if (typeof window === 'undefined') {
        return;
    }

    cleanup();
    vi.restoreAllMocks();
    vi.clearAllMocks();
    localStorage.clear();
    document.cookie = 'appearance=;path=/;max-age=0';
    document.documentElement.className = '';

    for (const key of Object.keys(document.documentElement.dataset)) {
        delete document.documentElement.dataset[key];
    }
});
