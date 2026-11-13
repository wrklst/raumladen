import { router } from '@inertiajs/react';

/**
 * The storefront's visitor statistics: cookieless and anonymous. Nothing is stored in the browser, no
 * cookie, no storage, no id that outlives the tab: the visit id below lives
 * in this script's memory only. The server keeps no address and no browser
 * string (App\Analytics\SiteVisits). Silent under Do Not Track and Global
 * Privacy Control, inside a frame, in the backend, and when statistics are
 * switched off in the shop settings.
 *
 * Batched, so the server sees about one request per visit: page views wait
 * here and leave together when the tab is hidden or closed, or once BATCH
 * have piled up.
 */
type PageView = {
    type: 'pageview';
    path: string;
    locale: string | null;
    at: number;
    seconds: number;
};

const BATCH = 20;
const ENDPOINT = '/site-events';
const CAMPAIGN = ['utm_source', 'utm_medium', 'utm_campaign'] as const;
// Not part of the shop a visitor sees: the backend and its sign-in.
const BACKEND =
    /^\/(admin|settings|login|invitation|two-factor|forgot-password|reset-password|user)(\/|$)/;

export function startSiteBeacon({
    win = window,
    nav = navigator,
    fetcher = fetch,
}: { win?: Window; nav?: Navigator; fetcher?: typeof fetch } = {}): boolean {
    const gpc = (nav as Navigator & { globalPrivacyControl?: boolean })
        .globalPrivacyControl;
    const enabled =
        win.document.querySelector('meta[name="site-statistics"]') !== null;

    if (!enabled || nav.doNotTrack === '1' || gpc || win.self !== win.top) {
        return false;
    }

    const random = new Uint8Array(16);
    crypto.getRandomValues(random);
    const visit = Array.from(
        random,
        (byte) => 'abcdefghijklmnopqrstuvwxyz0123456789'[byte % 36],
    ).join('');

    // The landing, read once: the campaign is only in the first address of a visit.
    const query = new URLSearchParams(win.location.search);
    const landing = {
        path: win.location.pathname,
        referrer: win.document.referrer || null,
        ...Object.fromEntries(
            CAMPAIGN.map((key) => [
                key,
                query.get(key)?.trim().slice(0, 128) || null,
            ]),
        ),
    };

    const queue: PageView[] = [];
    let page: {
        path: string;
        locale: string | null;
        at: number;
        visibleMs: number;
        since: number | null;
    } | null = null;

    // A page view is queued when it ends, with the seconds it was visible.
    const endPage = (): void => {
        if (!page) {
            return;
        }

        const visibleMs =
            page.visibleMs +
            (page.since === null ? 0 : Date.now() - page.since);
        queue.push({
            type: 'pageview',
            path: page.path,
            locale: page.locale,
            at: page.at,
            seconds: Math.min(7200, Math.round(visibleMs / 1000)),
        });
        page = null;
    };

    const flush = (): void => {
        if (!queue.length) {
            return;
        }

        const now = Date.now();
        const events = queue.splice(0, queue.length);
        const body = JSON.stringify({
            visit,
            locale: events[0].locale,
            landing,
            events: events.map(({ type, path, seconds, at }) => ({
                type,
                path,
                seconds,
                ago: Math.round((now - at) / 1000),
            })),
        });

        try {
            // credentials: 'omit' keeps even the session cookie out of the request.
            void fetcher(ENDPOINT, {
                method: 'POST',
                keepalive: true,
                credentials: 'omit',
                headers: { 'Content-Type': 'text/plain' },
                body,
            }).catch(() => {});
        } catch {
            // a lost batch is a visit not counted
        }
    };

    const startPage = (path: string, locale: unknown): void => {
        // Inertia announces the first page twice on load; search-as-you-type
        // changes only the query. The page already on screen is not a new view.
        if (page?.path === path) {
            return;
        }

        endPage();

        if (BACKEND.test(path)) {
            return;
        }

        page = {
            path,
            locale:
                typeof locale === 'string'
                    ? locale
                    : win.document.documentElement.lang || null,
            at: Date.now(),
            visibleMs: 0,
            since:
                win.document.visibilityState === 'hidden' ? null : Date.now(),
        };

        if (queue.length >= BATCH) {
            flush();
        }
    };

    startPage(win.location.pathname, null);

    router.on('navigate', (event) => {
        startPage(
            new URL(event.detail.page.url, win.location.origin).pathname,
            event.detail.page.props.locale,
        );
    });

    const leave = (): void => {
        const current = page;

        endPage();
        flush();

        // The tab may come back: the same page then counts as a new view from that moment.
        if (current && win.document.visibilityState !== 'hidden') {
            page = null;
        }
    };

    win.document.addEventListener('visibilitychange', () => {
        if (win.document.visibilityState === 'hidden') {
            leave();
        }
    });
    win.addEventListener('pagehide', leave);

    return true;
}
