import { expect, test, vi } from 'vite-plus/test';
import { loadSearchIndex, normalize, searchIndex } from '@/lib/search-index';
import type { SearchEntry } from '@/lib/search-index';
import type { ProductCard } from '@/types';

// Index text as the server delivers it: already normalised.
const entry = (
    id: number,
    title: string,
    fields: Partial<SearchEntry>,
): SearchEntry => ({
    card: { id, title } as ProductCard,
    title: normalize(title),
    authors: '',
    codes: '',
    text: '',
    ...fields,
});

const index = [
    entry(2, 'Zürich bauen', {
        title: 'zurich bauen building zurich',
        authors: 'ludwig engel, blanca borrell',
        codes: '978-3-69354-013-8 9783693540138 si-2',
        text: 'on space and the city.',
    }),
    entry(1, 'The Mourning of Space', {
        authors: 'blanca borrell',
        codes: '978-3-69354-012-1 9783693540121 si-1',
        text: 'olive landscapes of jaen.',
    }),
];

const titles = (query: string) =>
    searchIndex(index, query).map((card) => card.title);

// The same cases as tests/Feature/Shop/SearchTest.php: browser and server must agree.
test.each([
    ['mourning', ['The Mourning of Space']],
    ['  MOURNING   space ', ['The Mourning of Space']],
    ['Zürich', ['Zürich bauen']],
    ['zurich', ['Zürich bauen']],
    ['building', ['Zürich bauen']],
    ['engel', ['Zürich bauen']],
    ['978-3-69354-012-1', ['The Mourning of Space']],
    ['9783693540121', ['The Mourning of Space']],
    ['si-2', ['Zürich bauen']],
    ['Jaén', ['The Mourning of Space']],
    ['mourning zurich', []],
])('"%s" finds %j', (query, expected) => {
    expect(titles(query)).toEqual(expected);
});

test('a title hit ranks above a description hit; no query lists everything in catalogue order', () => {
    expect(titles('space')).toEqual(['The Mourning of Space', 'Zürich bauen']);
    expect(titles('')).toEqual(['Zürich bauen', 'The Mourning of Space']);
});

test('the query is folded like the server folds the index', () => {
    expect(normalize('  Straße  ÆRØ  Jaén ')).toBe('strasse aero jaen');
});

test('the index is downloaded once per URL and a failure falls back to the server', async () => {
    const fetchMock = vi
        .spyOn(globalThis, 'fetch')
        .mockResolvedValue({ ok: true, json: async () => index } as Response);

    expect(await loadSearchIndex('/de/search-index?v=1')).toEqual(index);
    expect(await loadSearchIndex('/de/search-index?v=1')).toEqual(index);
    expect(fetchMock).toHaveBeenCalledTimes(1);

    fetchMock.mockResolvedValue({ ok: false } as Response);

    expect(await loadSearchIndex('/de/search-index?v=2')).toBeNull();
});
