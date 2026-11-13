<?php

namespace App\Address;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Address suggestions while typing, from Photon (komoot, OpenStreetMap data),
 * asked by our server and never by the customer's browser: Photon then sees
 * one server instead of every customer's IP and keystrokes, and is not a
 * separate recipient in the privacy policy.
 *
 * photon.komoot.io is free under fair use ("extensive usage will be throttled
 * or completely banned"), so every answer is cached for 30 days, the empty
 * ones too: prefixes repeat ("Haupt", "Haupts", "Hauptst") and addresses
 * change on a scale of years. Four guards, each from a measurement against
 * the service:
 *
 *  1. No request below three characters.
 *  2. At most 120 characters (a 2,400 character query took 4.45 s).
 *  3. Five second timeout, then an empty list. The address fields must stay
 *     fully usable without suggestions.
 *  4. Ten minutes of silence after a failure, so not every keystroke at the
 *     checkout waits for the timeout again.
 *
 * Photon is a POI search, not an address database: `name` is a shop or
 * station name for houses, so only street, house number, postcode and city
 * are used. For street hits the street is in `name` and `street` is missing.
 *
 * The data is ODbL licensed; ATTRIBUTION has to be shown with the suggestions.
 */
class AddressSuggester
{
    public const string ENDPOINT = 'https://photon.komoot.io/api';

    public const string ATTRIBUTION = '© OpenStreetMap contributors (ODbL), via Photon/komoot';

    private const int MIN_LENGTH = 3;

    private const int MAX_LENGTH = 120;

    private const int LIMIT = 8;

    private const int TIMEOUT_SECONDS = 5;

    private const int CACHE_DAYS = 30;

    private const int OUTAGE_MINUTES = 10;

    private const string OUTAGE_KEY = 'address:outage';

    /**
     * @return list<array{address1: string, zip: string, city: string, country: string, label: string}>
     */
    public function suggest(string $input, ?string $country = null, string $locale = 'de'): array
    {
        $query = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $input)), 0, self::MAX_LENGTH);
        $country = preg_match('/^[A-Za-z]{2}$/', (string) $country) ? strtoupper((string) $country) : null;
        // Photon only speaks a few languages; anything else falls back to its default.
        $language = in_array($locale, ['de', 'en', 'fr'], true) ? $locale : 'en';

        if (mb_strlen($query) < self::MIN_LENGTH) {
            return [];
        }

        $key = 'address:'.md5(mb_strtolower($query).'|'.$country.'|'.$language);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return self::validSuggestions($cached);
        }

        if (Cache::has(self::OUTAGE_KEY)) {
            return [];
        }

        try {
            // Complete addresses first; streets only when no house matches,
            // because someone typing "Hauptstr" has not reached the number yet.
            $suggestions = $this->ask($query, $country, $language, 'house')
                ?: $this->ask($query, $country, $language, 'street');
        } catch (Throwable $e) {
            Cache::put(self::OUTAGE_KEY, true, now()->addMinutes(self::OUTAGE_MINUTES));
            Log::warning('Address suggestions unavailable: '.$e->getMessage());

            return [];
        }

        Cache::put($key, $suggestions, now()->addDays(self::CACHE_DAYS));

        return $suggestions;
    }

    /**
     * Keeps only well-formed entries of a cached payload.
     *
     * @param  array<array-key, mixed>  $cached
     * @return list<array{address1: string, zip: string, city: string, country: string, label: string}>
     */
    private static function validSuggestions(array $cached): array
    {
        $valid = [];

        foreach ($cached as $item) {
            if (is_array($item)
                && is_string($item['address1'] ?? null)
                && is_string($item['zip'] ?? null)
                && is_string($item['city'] ?? null)
                && is_string($item['country'] ?? null)
                && is_string($item['label'] ?? null)) {
                $valid[] = [
                    'address1' => $item['address1'],
                    'zip' => $item['zip'],
                    'city' => $item['city'],
                    'country' => $item['country'],
                    'label' => $item['label'],
                ];
            }
        }

        return $valid;
    }

    /**
     * @return list<array{address1: string, zip: string, city: string, country: string, label: string}>
     */
    private function ask(string $query, ?string $country, string $language, string $layer): array
    {
        $features = Http::timeout(self::TIMEOUT_SECONDS)
            ->withUserAgent(sprintf('%s address suggestions (+%s; %s)', config('app.name'), config('app.url'), config('shop.seller.email')))
            ->acceptJson()
            ->get(self::ENDPOINT, array_filter([
                'q' => $query,
                'limit' => self::LIMIT,
                'lang' => $language,
                'layer' => $layer,
                // One country only: Photon answers a comma list with 200 and an empty list.
                'countrycode' => $country,
            ]))
            ->throw()
            ->json('features', []);

        $suggestions = [];

        foreach ($features as $feature) {
            $suggestion = self::toAddress($feature['properties'] ?? [], $country);

            if ($suggestion !== null) {
                // layer=street returns one feature per OSM way: the same street several times.
                $suggestions[mb_strtolower($suggestion['label'])] = $suggestion;
            }
        }

        return array_values($suggestions);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array{address1: string, zip: string, city: string, country: string, label: string}|null
     */
    private static function toAddress(array $properties, ?string $country): ?array
    {
        $code = strtoupper((string) ($properties['countrycode'] ?? ''));
        $street = trim((string) ($properties['street'] ?? ''));

        if ($street === '' && ($properties['type'] ?? '') === 'street') {
            $street = trim((string) ($properties['name'] ?? ''));
        }

        $city = trim((string) ($properties['city'] ?? $properties['town'] ?? $properties['village'] ?? ''));

        if ($street === '' || $city === '' || ($country !== null && $code !== $country)) {
            return null;
        }

        $address1 = trim($street.' '.trim((string) ($properties['housenumber'] ?? '')));
        $zip = trim((string) ($properties['postcode'] ?? ''));

        return [
            'address1' => $address1,
            'zip' => $zip,
            'city' => $city,
            'country' => $code,
            'label' => $address1.', '.trim($zip.' '.$city),
        ];
    }
}
