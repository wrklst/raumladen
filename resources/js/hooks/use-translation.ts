import { usePage } from '@inertiajs/react';

type TranslationProps = {
    translations: Record<string, string>;
    locale: string;
    locales: string[];
};

/**
 * UI strings come from lang/{locale}.json (shared as `translations`).
 * Keys are the English source strings, like Laravel's __().
 */
export function useTrans() {
    const { translations, locale, locales } = usePage<TranslationProps>().props;

    const t = (
        key: string,
        params?: Record<string, string | number>,
    ): string => {
        let value = translations?.[key] ?? key;

        if (params) {
            for (const [name, replacement] of Object.entries(params)) {
                value = value.replaceAll(`:${name}`, String(replacement));
            }
        }

        return value;
    };

    return { t, locale, locales };
}
