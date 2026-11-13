import { Form, Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { index as orders } from '@/routes/shop/account/orders';
import { edit as profile } from '@/routes/shop/account/profile';

/**
 * Two-column shell for the customer account pages.
 */
export function AccountShell({
    title,
    children,
}: PropsWithChildren<{ title: string }>) {
    const { t } = useTrans();
    const { url } = usePage();

    const items = [
        { title: t('Orders'), href: orders(), active: !url.includes('/profile') },
        { title: t('Profile'), href: profile(), active: url.includes('/profile') },
    ];

    return (
        <div className="grid gap-8 md:grid-cols-[12rem_1fr]">
            <nav className="flex gap-2 md:flex-col" aria-label={t('Account')}>
                {items.map((item) => (
                    <Button
                        key={item.title}
                        asChild
                        variant="ghost"
                        className={cn('justify-start', item.active && 'bg-muted')}
                    >
                        <Link href={item.href}>{item.title}</Link>
                    </Button>
                ))}
                <Form {...logout.form()} className="md:mt-4">
                    <Button
                        type="submit"
                        variant="ghost"
                        className="w-full justify-start text-muted-foreground"
                    >
                        {t('Sign out')}
                    </Button>
                </Form>
            </nav>
            <section className="space-y-6">
                <h1 className="text-2xl font-semibold">{title}</h1>
                {children}
            </section>
        </div>
    );
}
