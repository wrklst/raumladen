import { router } from '@inertiajs/react';
import { useTrans } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { update } from '@/routes/locale';

const names: Record<string, string> = { de: 'Deutsch', en: 'English' };

/**
 * Switches the interface language of the backend and the auth pages.
 * The choice is stored on the user (signed in) and in the session.
 */
export default function LanguageSwitcher({
    className,
}: {
    className?: string;
}) {
    const { t, locale, locales } = useTrans();

    return (
        <nav
            aria-label={t('Language')}
            className={cn('flex gap-3 text-sm', className)}
        >
            {locales.map((code) => (
                <button
                    key={code}
                    type="button"
                    lang={code}
                    aria-current={code === locale ? 'true' : undefined}
                    className={cn(
                        'cursor-pointer hover:underline',
                        code === locale
                            ? 'font-semibold'
                            : 'text-muted-foreground',
                    )}
                    onClick={() =>
                        code !== locale &&
                        router.post(update(), { locale: code })
                    }
                >
                    {names[code] ?? code.toUpperCase()}
                </button>
            ))}
        </nav>
    );
}
