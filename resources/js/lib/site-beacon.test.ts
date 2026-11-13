import { beforeEach, expect, test, vi } from 'vite-plus/test';
import { startSiteBeacon } from '@/lib/site-beacon';

const meta = () => {
    const tag = document.createElement('meta');
    tag.name = 'site-statistics';
    tag.content = 'on';
    document.head.append(tag);
};

beforeEach(() => {
    document.head
        .querySelectorAll('meta[name="site-statistics"]')
        .forEach((tag) => tag.remove());
    window.history.replaceState(
        {},
        '',
        '/de/products/space?utm_source=newsletter&utm_campaign=herbst',
    );
});

const hide = () => {
    Object.defineProperty(document, 'visibilityState', {
        value: 'hidden',
        configurable: true,
    });
    document.dispatchEvent(new Event('visibilitychange'));
    Object.defineProperty(document, 'visibilityState', {
        value: 'visible',
        configurable: true,
    });
};

test('is silent unless statistics are on, and under Do Not Track or Global Privacy Control', () => {
    const fetcher = vi.fn().mockResolvedValue({});

    // Not switched on in the shop settings.
    expect(startSiteBeacon({ fetcher })).toBe(false);

    meta();

    expect(
        startSiteBeacon({ fetcher, nav: { doNotTrack: '1' } as Navigator }),
    ).toBe(false);
    expect(
        startSiteBeacon({
            fetcher,
            nav: { globalPrivacyControl: true } as unknown as Navigator,
        }),
    ).toBe(false);

    hide();

    expect(fetcher).not.toHaveBeenCalled();
});

test('sends the page view when the tab is hidden, without cookies, and stores nothing in the browser', () => {
    meta();
    const fetcher = vi.fn().mockResolvedValue({});

    expect(startSiteBeacon({ fetcher, nav: {} as Navigator })).toBe(true);
    expect(fetcher).not.toHaveBeenCalled();

    hide();

    expect(fetcher).toHaveBeenCalledTimes(1);

    const [url, options] = fetcher.mock.calls[0] as [string, RequestInit];
    const body = JSON.parse(options.body as string);

    expect(url).toBe('/site-events');
    expect(options).toMatchObject({
        method: 'POST',
        credentials: 'omit',
        keepalive: true,
    });
    expect(body.visit).toMatch(/^[a-z0-9]{16}$/);
    expect(body.landing).toMatchObject({
        path: '/de/products/space',
        utm_source: 'newsletter',
        utm_campaign: 'herbst',
    });
    expect(body.events).toHaveLength(1);
    expect(body.events[0]).toMatchObject({
        type: 'pageview',
        path: '/de/products/space',
    });
    // The query (and with it any search text) is not part of what is sent as the page.
    expect(body.events[0].path).not.toContain('?');

    expect(document.cookie).toBe('');
    expect(localStorage.length).toBe(0);
    expect(sessionStorage.length).toBe(0);
});

test('the backend is not counted', () => {
    meta();
    window.history.replaceState({}, '', '/admin/orders');
    const fetcher = vi.fn().mockResolvedValue({});

    startSiteBeacon({ fetcher, nav: {} as Navigator });
    hide();

    expect(fetcher).not.toHaveBeenCalled();
});
