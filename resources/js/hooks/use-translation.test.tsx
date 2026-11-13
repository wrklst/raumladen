import { renderHook } from '@testing-library/react';
import { expect, test } from 'vite-plus/test';
import { useTrans } from '@/hooks/use-translation';
import { setPage } from '@/test-utils';

test('translates, replaces placeholders and falls back to the key', () => {
    setPage({ translations: { ':count items': ':count Artikel' } });

    const { t } = renderHook(() => useTrans()).result.current;

    expect(t(':count items', { count: 3 })).toBe('3 Artikel');
    expect(t('Not translated :x', { x: 1 })).toBe('Not translated 1');
});
