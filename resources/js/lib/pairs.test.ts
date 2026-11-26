import { expect, test } from 'vite-plus/test';
import { joinPairs, parsePairs } from '@/lib/pairs';

test('credits written as lines become label and content groups, and back', () => {
    const text =
        'Editor: Ludwig Engel\nSeries editors: Ludwig Engel, María Mazzanti\n11.5 × 18 cm';
    const pairs = parsePairs(text);

    expect(pairs).toEqual([
        { label: 'Editor', content: 'Ludwig Engel' },
        { label: 'Series editors', content: 'Ludwig Engel, María Mazzanti' },
        { label: '', content: '11.5 × 18 cm' },
    ]);
    expect(joinPairs(pairs)).toBe(text);
});

test('empty groups are dropped and a label cannot break the format', () => {
    expect(
        joinPairs([
            { label: 'Design', content: '' },
            { label: 'Co: py\nediting', content: ' Sara Hoss ' },
        ]),
    ).toBe('Co py editing: Sara Hoss');
    expect(parsePairs(null)).toEqual([]);
});
