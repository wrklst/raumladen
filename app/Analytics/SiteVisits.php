<?php

namespace App\Analytics;

use App\Models\SiteEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The shop's visitor statistics: cookieless and anonymous by construction.
 *
 * The browser stores nothing (no cookie, no storage); resources/js/lib/
 * site-beacon.ts keeps a random visit id in the tab's memory and sends page
 * views in batches. Here nothing that identifies a person is kept: not the
 * address, not the browser string. A visitor is counted through
 * `substr(sha256(day|salt|ip|user-agent), 0, 32)` with a random salt per day
 * that lives in the cache for 48 hours only; once it is gone the key cannot
 * be derived again, so nobody is followed from one day to the next. Kept are
 * the path, the language, a device class, the referring site's host and the
 * campaign tags of the landing address.
 *
 * Paths are cleaned of anything personal (order ids); the search text is
 * never part of a path. Visitors who send Do Not Track or Global Privacy
 * Control are not counted (decided in the browser, where the signal is).
 *
 * ponytail: one insert per batch, straight into the database. Put a queue
 * or a Redis list in front when the shop sees more than a few visits a
 * second.
 */
class SiteVisits
{
    public const array TYPES = ['pageview'];

    private const int MAX_EVENTS = 50;

    private const int MAX_AGO_SECONDS = 1800;

    private const int SALT_HOURS = 48;

    /**
     * Validate one beacon body and store its events. Never throws: a hit
     * that cannot be stored is a hit not counted. Returns how many were kept.
     *
     * @param  array<mixed>  $payload
     */
    public static function record(array $payload, Request $request): int
    {
        $visit = $payload['visit'] ?? null;
        $userAgent = (string) $request->userAgent();

        if (! config('shop.analytics.enabled') || ! is_string($visit) || ! preg_match('/^[a-z0-9]{16}$/', $visit)
            || ! is_array($payload['events'] ?? null) || self::isBot($userAgent)) {
            return 0;
        }

        $landing = is_array($payload['landing'] ?? null) ? $payload['landing'] : [];

        $common = [
            'visit' => $visit,
            'visit_key' => self::dailyKey($request->ip(), $userAgent),
            'locale' => self::text($payload['locale'] ?? null, 8),
            'landing_path' => is_string($landing['path'] ?? null) && str_starts_with($landing['path'], '/') ? self::path($landing['path']) : null,
            'referrer_host' => self::referrerHost($landing['referrer'] ?? null, $request->getHost()),
            'utm_source' => self::text($landing['utm_source'] ?? null, 128),
            'utm_medium' => self::text($landing['utm_medium'] ?? null, 128),
            'utm_campaign' => self::text($landing['utm_campaign'] ?? null, 128),
            'device' => self::device($userAgent),
        ];

        $rows = [];

        foreach (array_slice(array_values($payload['events']), 0, self::MAX_EVENTS) as $event) {
            if (! is_array($event) || ! in_array($event['type'] ?? null, self::TYPES, true)
                || ! is_string($event['path'] ?? null) || ! str_starts_with($event['path'], '/')) {
                continue;
            }

            $rows[] = [
                'type' => $event['type'],
                'path' => self::path($event['path']),
                'seconds' => isset($event['seconds']) ? min(7200, max(0, (int) $event['seconds'])) : null,
                'created_at' => now()->subSeconds(max(0, min(self::MAX_AGO_SECONDS, (int) ($event['ago'] ?? 0)))),
            ] + $common;
        }

        if ($rows === []) {
            return 0;
        }

        try {
            SiteEvent::query()->insert($rows);
        } catch (Throwable $e) {
            report($e);

            return 0;
        }

        return count($rows);
    }

    /**
     * The anonymous key of one browser for one UTC day.
     */
    public static function dailyKey(?string $ip, string $userAgent): string
    {
        $day = now('UTC')->toDateString();

        return substr(hash('sha256', implode('|', [$day, self::salt($day), $ip, $userAgent])), 0, 32);
    }

    /**
     * The day's salt: made on first use, kept 48 hours in the cache, never written anywhere else.
     */
    private static function salt(string $day): string
    {
        $key = 'site-visits:salt:'.$day;
        Cache::add($key, bin2hex(random_bytes(32)), now()->addHours(self::SALT_HOURS));

        return (string) Cache::get($key);
    }

    /**
     * The path without query and hash, and without ids that belong to one person's order.
     */
    public static function path(string $path): string
    {
        $path = strtok($path, '?#') ?: '/';
        $path = (string) preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', ':id', $path);
        $path = (string) preg_replace('#/(invoices|invitation)/\d+#', '/$1/:id', $path);

        return mb_substr($path, 0, 255);
    }

    public static function isBot(string $userAgent): bool
    {
        return $userAgent === '' || (bool) preg_match('/bot|crawl|spider|slurp|headless|lighthouse|preview|monitor|curl|wget|python|httpclient/i', $userAgent);
    }

    /**
     * `phone`, `tablet` or `desktop` from the browser string; nothing else of it is kept.
     */
    public static function device(string $userAgent): string
    {
        if (preg_match('/ipad|tablet|android(?!.*mobile)/i', $userAgent)) {
            return 'tablet';
        }

        return preg_match('/mobi|iphone|ipod|android/i', $userAgent) ? 'phone' : 'desktop';
    }

    /**
     * The host of the referring site, or null for none, a bad one and the shop itself.
     */
    private static function referrerHost(mixed $referrer, string $ownHost): ?string
    {
        $host = is_string($referrer) ? parse_url($referrer, PHP_URL_HOST) : null;

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = (string) preg_replace('/^www\./', '', mb_substr(strtolower($host), 0, 190));

        return $host === preg_replace('/^www\./', '', strtolower($ownHost)) ? null : $host;
    }

    private static function text(mixed $value, int $limit): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
