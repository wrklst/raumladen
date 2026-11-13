<?php

namespace App\Tax;

use App\Support\Countries;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Checks EU VAT IDs against VIES. Fails toward charging VAT: an unavailable
 * service never produces a valid result.
 *
 * ponytail: VIES simple check. A German seller should move to the BZSt
 * qualified confirmation (api.evatr.vies.bzst.de) for audit-proof evidence.
 */
class VatIdValidator
{
    public const string ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

    /** @var list<string> */
    private const array RETRY_ERRORS = [
        'MS_UNAVAILABLE', 'MS_MAX_CONCURRENT_REQ', 'MS_MAX_CONCURRENT_REQ_TIME', 'GLOBAL_MAX_CONCURRENT_REQ',
        'GLOBAL_MAX_CONCURRENT_REQ_TIME', 'SERVICE_UNAVAILABLE', 'SERVER_BUSY', 'TIMEOUT',
    ];

    public static function normalize(string $vatId): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $vatId));
    }

    public function validate(string $vatId): VatIdResult
    {
        $vatId = self::normalize($vatId);

        if (! preg_match('/^([A-Z]{2})([A-Z0-9]{2,12})$/', $vatId, $m)) {
            return VatIdResult::invalid($vatId);
        }

        $country = Countries::fromVatPrefix($m[1]);

        if (! Countries::isEu($country)) {
            return VatIdResult::invalid($vatId, $country);
        }

        $cached = Cache::get($this->cacheKey($vatId));

        if ($cached !== null) {
            return new VatIdResult($vatId, $country, (bool) $cached);
        }

        try {
            $response = Http::timeout(10)->acceptJson()->post(self::ENDPOINT, [
                'countryCode' => $m[1],
                'vatNumber' => $m[2],
            ]);
        } catch (ConnectionException) {
            return VatIdResult::unavailable($vatId, $country);
        }

        $body = $response->json() ?? [];
        $error = $body['errorWrappers'][0]['error'] ?? null;

        if ($response->serverError() || $response->status() === 429 || in_array($error, self::RETRY_ERRORS, true)) {
            return VatIdResult::unavailable($vatId, $country);
        }

        $valid = ($body['valid'] ?? false) === true;

        Cache::put($this->cacheKey($vatId), $valid, $valid ? now()->addDay() : now()->addHour());

        return new VatIdResult($vatId, $country, $valid);
    }

    private function cacheKey(string $vatId): string
    {
        return 'vies:'.$vatId;
    }
}
