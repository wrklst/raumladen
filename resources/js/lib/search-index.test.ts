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
        authors: 'jonas weber, anna beispiel',
        codes: '978-3-00-000002-8 9783000000028 si-2',
        text: 'on space and the city.',
    }),
    entry(1, 'The Morning of Space', {
        authors: 'anna beispiel',
        codes: '978-3-00-000001-1 9783000000011 si-1',
        text: 'olive landscapes of jaen.',
    }),
];

const titles = (query: string) =>
    searchIndex(index, query).map((card) => card.title);

// The same cases as tests/Feature/Shop/SearchTest.php: browser and server must agree.
test.each([
    ['morning', ['The Morning of Space']],
    ['  MORNING   space ', ['The Morning of Space']],
    ['Zürich', ['Zürich bauen']],
    ['zurich', ['Zürich bauen']],
    ['building', ['Zürich bauen']],
    ['weber', ['Zürich bauen']],
    ['978-3-00-000001-1', ['The Morning of Space']],
    ['9783000000011', ['The Morning of Space']],
    ['si-2', ['Zürich bauen']],
    ['Jaén', ['The Morning of Space']],
    ['morning zurich', []],
])('"%s" finds %j', (query, expected) => {
    expect(titles(query)).toEqual(expected);
});

test('a title hit ranks above a description hit; no query lists everything in catalogue order', () => {
    expect(titles('space')).toEqual(['The Morning of Space', 'Zürich bauen']);
    expect(titles('')).toEqual(['Zürich bauen', 'The Morning of Space']);
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
