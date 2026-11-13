<?php

namespace App\Settings;

use App\Models\Page;

/**
 * Placeholders in the text of content pages (":name", ":vat_id", ...), filled
 * in when a page is shown. The legal texts therefore always state what is set
 * under Shop settings; whatever is not set yet appears as "[TODO: ...]" and
 * is listed by the go-live check.
 */
class PageVariables
{
    /**
     * @return array<string, string> placeholder => text
     */
    public static function for(Page $page, string $locale): array
    {
        $delivery = function (int $zone) use ($locale): string {
            $days = config("shop.shipping.{$zone}.delivery_days");

            return $days === null ? '' : __(':min–:max working days', ['min' => $days[0], 'max' => $days[1]], $locale);
        };

        // The order does not matter: strtr() always takes the longest match.
        $values = [
            'name' => [config('shop.seller.name'), 'Name'],
            'address1' => [config('shop.seller.address1'), 'Street and number'],
            'zip' => [config('shop.seller.zip'), 'Postal code'],
            'city' => [config('shop.seller.city'), 'City'],
            'email' => [config('shop.seller.email'), 'Email address'],
            'phone' => [config('shop.seller.phone'), 'Phone'],
            'register' => [config('shop.seller.register'), 'Register entry'],
            'representatives' => [config('shop.seller.representatives'), 'Represented by'],
            'vat_id' => [config('shop.seller.vat_id'), 'VAT ID'],
            'processor_name' => [config('shop.processor.name'), 'Company'],
            'processor_address' => [config('shop.processor.address'), 'Address'],
            'processor_location' => [config('shop.processor.location'), 'Server location'],
            'log_retention' => [config('shop.processor.log_retention'), 'Server logs are deleted after'],
            'carrier' => [config('shop.carrier'), 'Carrier'],
            'delivery_eu' => [$delivery(1), 'Delivery time EU'],
            'delivery_world' => [$delivery(2), 'Delivery time rest of the world'],
            'date' => [$page->updated_at?->format('d.m.Y'), 'Date'],
        ];

        $replacements = [];

        foreach ($values as $key => [$value, $label]) {
            $replacements[':'.$key] = (string) $value !== '' ? (string) $value : '[TODO: '.__($label, [], $locale).']';
        }

        return $replacements;
    }
}
