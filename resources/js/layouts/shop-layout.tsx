import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search, ShoppingBag, UserRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { PropsWithChildren } from 'react';
import { DisplaySettings } from '@/components/shop/display-settings';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-translation';
import { loadSearchIndex, searchIndex } from '@/lib/search-index';
import { setSiteName } from '@/lib/site';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { home } from '@/routes/shop';
import { login } from '@/routes/shop/account';
import { index as accountOrders } from '@/routes/shop/account/orders';
import { index as cartIndex } from '@/routes/shop/cart';
import { show as pageShow } from '@/routes/shop/pages';
import { create as withdraw } from '@/routes/shop/withdrawal';
import type { ShopSharedProps } from '@/types';
import { setUrlDefaults } from '@/wayfinder';

const languageName = (code: string): string =>
    new Intl.DisplayNames([code], { type: 'language' }).of(code) ?? code;

export default function ShopLayout({ children }: PropsWithChildren) {
    const { props, url } = usePage<
        ShopSharedProps & {
            name: string;
            search_index_url: string | null;
            errors?: Record<string, string>;
        }
    >();
    const { locale, locales, shop, cart_count, footer_pages, seo, auth } =
        props;

    // Wayfinder route helpers need the locale before any child renders.
    setUrlDefaults({ locale });
    setSiteName(props.name);

    const { t } = useTrans();
    const title = seo?.title ?? shop.name;

    // Client-side navigation is silent for assistive technology: move focus
    // to the new content and announce its title (WCAG 2.4.3, 4.1.3).
    const path = url.split('?')[0];
    const previousPath = useRef(path);
    const [announcement, setAnnouncement] = useState('');

    useEffect(() => {
        if (previousPath.current === path) {
            return;
        }

        previousPath.current = path;
        // Typing in the search leads here too; the caret must stay in the field.
        if (document.activeElement?.id !== 'site-search') {
            document.getElementById('main')?.focus();
        }

        setAnnouncement(title);
    }, [path, title]);

    // The theme class is on <html> from the server; keep it right when the
    // visitor moves between storefront and admin without a page load.
    useEffect(() => {
        document.documentElement.classList.add('theme-space-issues');

        return () =>
            document.documentElement.classList.remove('theme-space-issues');
    }, []);

    // Search as you type: results follow the input after a short pause. One
    // letter is noise; an emptied field returns to the full catalogue.
    const searchTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const onCatalogue = path === home().url;

    const searchAsYouType = (value: string): void => {
        clearTimeout(searchTimer.current);

        const query = value.trim();

        if (query.length === 1 || (query === '' && !onCatalogue)) {
            return;
        }

        const url = `${home().url}${query === '' ? '' : `?q=${encodeURIComponent(query)}`}`;

        // Small catalogue: the index is in the browser, so show the results
        // without a request. No pause needed either.
        if (props.search_index_url) {
            void loadSearchIndex(props.search_index_url).then((index) => {
                if (index === null) {
                    return askServer(query);
                }

                const hits = searchIndex(index, query);

                // From another page this is a new history entry; further typing replaces it.
                router[onCatalogue ? 'replace' : 'push']({
                    component: 'shop/home',
                    url,
                    props: (current) => ({
                        ...current,
                        products: {
                            data: hits,
                            meta: { links: [], total: hits.length },
                        },
                        filters: { q: query, author: '' },
                        seo: {
                            ...(current.seo as object),
                            title:
                                query === ''
                                    ? t('Books')
                                    : t('Search: :query', { query }),
                        },
                    }),
                    preserveScroll: true,
                    preserveState: true,
                });
            });

            return;
        }

        searchTimer.current = setTimeout(() => askServer(query), 150);
    };

    const askServer = (query: string): void => {
        router.get(home().url, query === '' ? {} : { q: query }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            // Already on the catalogue: fetch the list only, not the shared data.
            only: onCatalogue ? ['products', 'filters', 'seo'] : [],
        });
    };

    // After a failed submit, take the visitor to the first invalid field.
    const errors = props.errors;

    useEffect(() => {
        if (errors && Object.keys(errors).length > 0) {
            document
                .querySelector<HTMLElement>('main [aria-invalid="true"]')
                ?.focus();
        }
    }, [errors]);

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <Head title={title} />

            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-background focus:px-4 focus:py-2 focus:shadow-md"
            >
                {t('Skip to content')}
            </a>

            <header className="sticky top-0 z-40 border-b bg-background">
                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3 sm:px-6">
                    <Link href={home()} className="text-lg">
                        {shop.name}
                    </Link>

                    <form
                        role="search"
                        action={home().url}
                        method="get"
                        className="order-last flex w-full gap-1 sm:order-none sm:w-auto sm:flex-1 sm:justify-center"
                    >
                        <label htmlFor="site-search" className="sr-only">
                            {t('Search issues')}
                        </label>
                        <input
                            id="site-search"
                            type="search"
                            name="q"
                            onChange={(event) =>
                                searchAsYouType(event.target.value)
                            }
                            onFocus={() => {
                                if (props.search_index_url) {
                                    void loadSearchIndex(
                                        props.search_index_url,
                                    );
                                }
                            }}
                            defaultValue={
                                new URLSearchParams(url.split('?')[1]).get(
                                    'q',
                                ) ?? ''
                            }
                            placeholder={t('Search')}
                            maxLength={100}
                            className="h-8 w-full border border-input bg-background px-2 text-sm sm:max-w-xs"
                        />
                        <Button type="submit" variant="outline" size="sm">
                            <Search className="size-4" />
                            <span className="sr-only">{t('Search')}</span>
                        </Button>
                    </form>

                    <nav
                        className="flex flex-wrap items-center gap-1 sm:gap-2"
                        aria-label={t('Main navigation')}
                    >
                        <ul
                            className="mr-2 flex items-center gap-1 text-sm"
                            aria-label={t('Language')}
                        >
                            {locales.map((code) => (
                                <li key={code}>
                                    <a
                                        href={
                                            seo?.alternates?.[code] ??
                                            home({ locale: code }).url
                                        }
                                        hrefLang={code}
                                        lang={code}
                                        className={cn(
                                            'block px-2 py-1 underline-offset-4 hover:underline',
                                            code === locale &&
                                                'underline decoration-2',
                                        )}
                                        aria-current={
                                            code === locale ? 'true' : undefined
                                        }
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="uppercase"
                                        >
                                            {code}
                                        </span>
                                        <span className="sr-only">
                                            {languageName(code)}
                                        </span>
                                    </a>
                                </li>
                            ))}
                        </ul>

                        <DisplaySettings />

                        <Button variant="ghost" size="sm" asChild>
                            <Link href={auth.user ? accountOrders() : login()}>
                                <UserRound className="size-4" />
                                <span className="sr-only sm:not-sr-only">
                                    {auth.user ? t('Account') : t('Sign in')}
                                </span>
                            </Link>
                        </Button>

                        <Button variant="ghost" size="sm" asChild>
                            <Link href={cartIndex()}>
                                <ShoppingBag className="size-4" />
                                <span className="sr-only sm:not-sr-only">
                                    {t('Cart')}
                                </span>
                                {cart_count > 0 && (
                                    <Badge variant="outline">
                                        <span aria-hidden="true">
                                            {cart_count}
                                        </span>
                                        <span className="sr-only">
                                            {t(':count items', {
                                                count: cart_count,
                                            })}
                                        </span>
                                    </Badge>
                                )}
                            </Link>
                        </Button>
                    </nav>
                </div>
            </header>

            <main
                id="main"
                tabIndex={-1}
                className="w-full flex-1 px-4 py-6 sm:px-6"
            >
                {children}
            </main>

            <footer className="border-t">
                <div className="flex flex-col gap-3 px-4 py-4 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p>
                        © {new Date().getFullYear()} {shop.name}
                    </p>
                    <nav
                        className="flex flex-wrap gap-x-4 gap-y-1"
                        aria-label={t('Legal')}
                    >
                        <Link
                            href={withdraw()}
                            className="underline-offset-4 hover:underline"
                        >
                            {t('Withdraw from contract')}
                        </Link>
                        {footer_pages.map((page) => (
                            <Link
                                key={page.slug}
                                href={pageShow({ page: page.slug })}
                                className="underline-offset-4 hover:underline"
                            >
                                {page.title}
                            </Link>
                        ))}
                        {auth.user?.is_staff && (
                            // Plain link: the admin has its own theme and fonts.
                            <a
                                href={dashboard().url}
                                className="underline-offset-4 hover:underline"
                            >
                                Admin
                            </a>
                        )}
                    </nav>
                </div>
            </footer>

            <div role="status" aria-live="polite" className="sr-only">
                {announcement}
            </div>
        </div>
    );
}
