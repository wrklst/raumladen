import { Head, Link, usePage } from '@inertiajs/react';
import { ShoppingBag, UserRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { home } from '@/routes/shop';
import { login } from '@/routes/shop/account';
import { index as accountOrders } from '@/routes/shop/account/orders';
import { index as cartIndex } from '@/routes/shop/cart';
import { show as pageShow } from '@/routes/shop/pages';
import type { ShopSharedProps } from '@/types';
import { setUrlDefaults } from '@/wayfinder';

export default function ShopLayout({ children }: PropsWithChildren) {
    const { locale, locales, shop, cart_count, footer_pages, seo, auth } =
        usePage<ShopSharedProps>().props;

    // Wayfinder route helpers need the locale before any child renders.
    setUrlDefaults({ locale });

    const { t } = useTrans();

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <Head title={seo?.title ?? shop.name} />

            <header className="border-b">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
                    <Link
                        href={home()}
                        className="text-lg font-semibold tracking-tight"
                    >
                        {shop.name}
                    </Link>

                    <nav
                        className="flex items-center gap-1 sm:gap-2"
                        aria-label={t('Main navigation')}
                    >
                        <div
                            className="mr-2 flex items-center gap-1 text-sm"
                            aria-label={t('Language')}
                        >
                            {locales.map((code) => (
                                <a
                                    key={code}
                                    href={
                                        seo?.alternates?.[code] ??
                                        home({ locale: code }).url
                                    }
                                    hrefLang={code}
                                    className={cn(
                                        'rounded px-2 py-1 uppercase hover:bg-muted',
                                        code === locale &&
                                            'bg-muted font-semibold',
                                    )}
                                    aria-current={
                                        code === locale ? 'true' : undefined
                                    }
                                >
                                    {code}
                                </a>
                            ))}
                        </div>

                        <Button variant="ghost" size="sm" asChild>
                            <Link
                                href={
                                    auth.user ? accountOrders() : login()
                                }
                            >
                                <UserRound className="size-4" />
                                <span className="hidden sm:inline">
                                    {auth.user
                                        ? t('Account')
                                        : t('Sign in')}
                                </span>
                            </Link>
                        </Button>

                        <Button variant="ghost" size="sm" asChild>
                            <Link href={cartIndex()}>
                                <ShoppingBag className="size-4" />
                                <span className="hidden sm:inline">
                                    {t('Cart')}
                                </span>
                                {cart_count > 0 && (
                                    <Badge variant="secondary">
                                        {cart_count}
                                    </Badge>
                                )}
                            </Link>
                        </Button>
                    </nav>
                </div>
            </header>

            <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
                {children}
            </main>

            <footer className="border-t bg-muted/30">
                <div className="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-6 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                    <p>
                        © {new Date().getFullYear()} {shop.name}
                    </p>
                    <nav
                        className="flex flex-wrap gap-x-4 gap-y-1"
                        aria-label={t('Legal')}
                    >
                        {footer_pages.map((page) => (
                            <Link
                                key={page.slug}
                                href={pageShow({ page: page.slug })}
                                className="hover:text-foreground"
                            >
                                {page.title}
                            </Link>
                        ))}
                        {auth.user?.is_admin && (
                            <Link
                                href={dashboard()}
                                className="hover:text-foreground"
                            >
                                Admin
                            </Link>
                        )}
                    </nav>
                </div>
            </footer>
        </div>
    );
}
