import { render, screen } from '@testing-library/react';
import { expect, test, vi } from 'vite-plus/test';
import ShopLayout from '@/layouts/shop-layout';
import { setPage } from '@/test-utils';

const seo = (title: string) => ({
    title,
    alternates: { de: 'https://shop.test/de/x', en: 'https://shop.test/en/x' },
});

test('landmarks, skip link and named header links', () => {
    setPage({ seo: seo('Books'), cart_count: 2 });

    render(
        <ShopLayout>
            <h1>Books</h1>
        </ShopLayout>,
    );

    expect(
        screen
            .getByRole('link', { name: 'Skip to content' })
            .getAttribute('href'),
    ).toBe('#main');
    expect(screen.getByRole('main').id).toBe('main');
    expect(screen.getByRole('banner')).toBeTruthy();
    expect(screen.getByRole('contentinfo')).toBeTruthy();
    expect(
        screen.getByRole('navigation', { name: 'Main navigation' }),
    ).toBeTruthy();
    // Icon links keep their names when the text is visually hidden on phones.
    expect(screen.getByRole('link', { name: 'Sign in' })).toBeTruthy();
    expect(screen.getByRole('link', { name: /^Cart\s*2 items$/ })).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Impressum' })).toBeTruthy();
    // The withdrawal function has to be reachable from every page.
    expect(
        screen.getByRole('link', { name: 'Withdraw from contract' }),
    ).toBeTruthy();
});

test('the header search is a labelled form that keeps the current query', () => {
    setPage({ seo: seo('Search') }, '/de?q=morning');

    render(<ShopLayout>results</ShopLayout>);

    const search = screen.getByRole('search');
    const input = screen.getByRole('searchbox', { name: 'Search issues' });

    expect(search.getAttribute('method')).toBe('get');
    expect(input.getAttribute('name')).toBe('q');
    expect(input).toHaveProperty('value', 'morning');
    expect(screen.getByRole('button', { name: 'Search' })).toBeTruthy();
});

test('the language switcher links to this page in each language', () => {
    setPage({ seo: seo('Books') });

    render(<ShopLayout>content</ShopLayout>);

    const german = screen.getByRole('link', { name: 'Deutsch' });
    const english = screen.getByRole('link', { name: 'English' });

    expect(german.getAttribute('aria-current')).toBe('true');
    expect(english.getAttribute('href')).toBe('https://shop.test/en/x');
    expect(english.getAttribute('lang')).toBe('en');
    expect(english.getAttribute('hreflang')).toBe('en');
});

test('navigating to another page moves focus to main and announces the title', () => {
    setPage({ seo: seo('Books') }, '/de');

    const view = render(<ShopLayout>home</ShopLayout>);

    expect(document.activeElement).toBe(document.body);
    expect(screen.getByRole('status').textContent).toBe('');

    setPage({ seo: seo('Cart') }, '/de/cart');
    view.rerender(<ShopLayout>cart</ShopLayout>);

    expect(document.activeElement).toBe(screen.getByRole('main'));
    expect(screen.getByRole('status').textContent).toBe('Cart');
});

test('a reload of the same page keeps focus where it is', () => {
    setPage({ seo: seo('Checkout') }, '/de/checkout');

    const view = render(
        <ShopLayout>
            <input aria-label="VAT ID" />
        </ShopLayout>,
    );

    screen.getByRole('textbox', { name: 'VAT ID' }).focus();
    setPage({ seo: seo('Checkout') }, '/de/checkout?vat_id=ATU1');
    view.rerender(
        <ShopLayout>
            <input aria-label="VAT ID" />
        </ShopLayout>,
    );

    expect(document.activeElement).toBe(
        screen.getByRole('textbox', { name: 'VAT ID' }),
    );
});

test('validation errors move focus to the first invalid field', () => {
    setPage({ seo: seo('Checkout') }, '/de/checkout');

    const view = render(
        <ShopLayout>
            <input aria-label="Email" />
        </ShopLayout>,
    );

    setPage(
        { seo: seo('Checkout'), errors: { email: 'Required.' } },
        '/de/checkout',
    );
    view.rerender(
        <ShopLayout>
            <input aria-label="Email" aria-invalid="true" />
        </ShopLayout>,
    );

    expect(document.activeElement).toBe(
        screen.getByRole('textbox', { name: 'Email' }),
    );
});

test('typing in the search loads results after a pause, without stealing the caret', async () => {
    const { router } = await import('@inertiajs/react');
    // The mock from test-setup.ts, held as a plain function.
    const get = vi.mocked(
        router as unknown as { get: (...args: unknown[]) => void },
    ).get;
    const user = (await import('@testing-library/user-event')).default.setup();

    setPage({ seo: seo('Imprint') }, '/de/pages/imprint');

    const view = render(<ShopLayout>imprint</ShopLayout>);
    const input = screen.getByRole('searchbox', { name: 'Search issues' });

    await user.type(input, 'm');
    await new Promise((resolve) => setTimeout(resolve, 200));

    // One letter is not a search.
    expect(get).not.toHaveBeenCalled();

    await user.type(input, 'orning');
    await new Promise((resolve) => setTimeout(resolve, 200));

    // Seven more keystrokes, one request.
    expect(get).toHaveBeenCalledTimes(1);
    expect(get.mock.calls[0][1]).toEqual({ q: 'morning' });

    // The results page arrives: focus stays in the field instead of jumping to the content.
    setPage({ seo: seo('Search: morning') }, '/de?q=morning');
    view.rerender(<ShopLayout>results</ShopLayout>);

    expect(document.activeElement).toBe(input);
});

test('with the index in the browser, typing shows results at once and asks the server nothing', async () => {
    const { router } = await import('@inertiajs/react');
    const mocks = vi.mocked(
        router as unknown as Record<
            'get' | 'push' | 'replace',
            (...args: unknown[]) => void
        >,
    );
    const user = (await import('@testing-library/user-event')).default.setup();
    const card = { id: 1, title: 'The Morning of Space' };

    vi.spyOn(globalThis, 'fetch').mockResolvedValue({
        ok: true,
        json: async () => [
            {
                card,
                title: 'the morning of space',
                authors: '',
                codes: '',
                text: '',
            },
            {
                card: { id: 2, title: 'Other' },
                title: 'other',
                authors: '',
                codes: '',
                text: '',
            },
        ],
    } as Response);
    setPage(
        { seo: seo('Imprint'), search_index_url: '/de/search-index?v=a' },
        '/de/pages/imprint',
    );

    render(<ShopLayout>imprint</ShopLayout>);

    await user.type(
        screen.getByRole('searchbox', { name: 'Search issues' }),
        'mo',
    );
    await vi.waitFor(() => expect(mocks.push).toHaveBeenCalled());

    const visit = mocks.push.mock.calls[0][0] as {
        component: string;
        url: string;
        props: (current: object) => {
            products: { data: unknown[]; meta: { total: number } };
            filters: object;
        };
    };
    const next = visit.props({ seo: {} });

    // From another page: a new history entry on the catalogue, filled from the local index.
    expect(visit).toMatchObject({ component: 'shop/home', url: '/de?q=mo' });
    expect(next.products).toEqual({
        data: [card],
        meta: { links: [], total: 1 },
    });
    expect(next.filters).toEqual({ q: 'mo', author: '' });
    expect(mocks.get).not.toHaveBeenCalled();
});
