import type { ProductCard } from '@/types';

/**
 * Catalogue search in the browser, for small catalogues: the index is
 * downloaded once (the browser caches it, the URL changes with the
 * catalogue) and every keystroke is answered locally.
 *
 * Mirrors ProductSearch::score() (app/Shop/ProductSearch.php) and normalize(): the server
 * normalises the index text, this file normalises the query the same way and
 * applies the same weights, so both give the same results in the same order.
 */
export type SearchEntry = {
    card: ProductCard;
    title: string;
    authors: string;
    codes: string;
    text: string;
};

const loaded = new Map<string, Promise<SearchEntry[] | null>>();

/** Resolves to null when the index cannot be had; the caller then asks the server. */
export function loadSearchIndex(url: string): Promise<SearchEntry[] | null> {
    let index = loaded.get(url);

    if (!index) {
        index = fetch(url, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : null))
            .catch(() => null);
        loaded.set(url, index);
    }

    return index;
}

/** Lower case, accents and umlauts folded, whitespace collapsed (Str::ascii on the server). */
export function normalize(text: string): string {
    return text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .replace(/ß/g, 'ss')
        .replace(/[æÆ]/g, 'ae')
        .replace(/[øØ]/g, 'o')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

/** Every word has to match; title 10, author and codes 6, description 1; ties keep catalogue order. */
export function searchIndex(
    index: SearchEntry[],
    query: string,
): ProductCard[] {
    const words = normalize(query).split(' ').filter(Boolean);
    const hits: { score: number; position: number; card: ProductCard }[] = [];

    index.forEach((entry, position) => {
        const fields: [number, string][] = [
            [10, entry.title],
            [6, entry.authors],
            [6, entry.codes],
            [1, entry.text],
        ];
        let score = 0;

        for (const word of words) {
            const best = Math.max(
                0,
                ...fields
                    .filter(([, text]) => text.includes(word))
                    .map(([weight]) => weight),
            );

            if (best === 0) {
                return;
            }

            score += best;
        }

        hits.push({ score, position, card: entry.card });
    });

    return hits
        .sort((a, b) => b.score - a.score || a.position - b.position)
        .map((hit) => hit.card);
}
