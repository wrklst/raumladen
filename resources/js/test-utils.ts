/**
 * Test helpers. `@inertiajs/react` is mocked in test-setup.ts; components
 * read the page from here.
 */
export const sharedProps = {
    name: 'Raumladen',
    theme: { class: 'theme-default', high_contrast: false, no_motion: false },
    locale: 'de',
    locales: ['de', 'en'],
    translations: {} as Record<string, string>,
    shop: { name: 'Testverlag', currency: 'EUR' },
    cart_count: 0,
    footer_pages: [{ slug: 'imprint', title: 'Impressum' }],
    auth: { user: null },
    errors: {} as Record<string, string>,
};

type Page = { props: Record<string, unknown>; url: string };

const store = globalThis as unknown as { __inertiaPage: Page };

export function setPage(
    props: Record<string, unknown> = {},
    url = '/de',
): void {
    store.__inertiaPage = { props: { ...sharedProps, ...props }, url };
}

export function currentPage(): Page {
    return store.__inertiaPage;
}
