/**
 * Date as the locale writes it (de: 13.11.2026, en: 11/13/2026), or a dash for no date.
 */
export function formatDate(
    iso: string | null | undefined,
    locale = 'de',
): string {
    return iso ? new Date(iso).toLocaleDateString(locale) : '—';
}
