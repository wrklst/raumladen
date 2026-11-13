<?php

namespace App\Settings;

use App\Models\Page;

/**
 * What still stands between this installation and selling: missing seller
 * details, payment, mail, legal texts with placeholders. Shown on the
 * settings page and by `php artisan shop:check`.
 */
class GoLiveCheck
{
    /**
     * @return list<string>
     */
    public static function problems(): array
    {
        return [...self::settings(), ...self::pages()];
    }

    /**
     * @return list<string>
     */
    private static function settings(): array
    {
        $seller = config('shop.seller');
        $problems = [];

        $labels = ['name' => 'Name', 'address1' => 'Street and number', 'zip' => 'Postal code', 'city' => 'City', 'email' => 'Email address', 'register' => 'Register entry', 'representatives' => 'Represented by', 'iban' => 'IBAN', 'bic' => 'BIC', 'bank' => 'Bank'];

        foreach ($labels as $key => $label) {
            if (trim((string) $seller[$key]) === '') {
                $problems[] = __('Seller: :field is not set.', ['field' => __($label)]);
            }
        }

        if (! preg_match('/\d/', (string) $seller['address1'])) {
            $problems[] = __('Seller: the address has no house number.');
        }

        // § 14 Abs. 4 Nr. 2 UStG: an invoice without either is not a valid invoice.
        if ((string) $seller['vat_id'] === '' && (string) $seller['tax_number'] === '') {
            $problems[] = __('Seller: neither VAT ID nor tax number is set, so invoices would be invalid.');
        }

        if (str_ends_with((string) $seller['email'], 'example.org')) {
            $problems[] = __('Seller: the email is still the example address.');
        }

        foreach (['address' => 'Address', 'location' => 'Server location', 'log_retention' => 'Server logs are deleted after'] as $key => $label) {
            if ((string) config("shop.processor.{$key}") === '') {
                $problems[] = __('Hosting: :field is not set.', ['field' => __($label)]);
            }
        }

        if ((string) config('shop.carrier') === '') {
            $problems[] = __('Shipping: no carrier is set.');
        }

        $mollie = (string) config('shop.payments.mollie.api_key');

        if ($mollie === '') {
            $problems[] = __('Payment: no Mollie key is set, so only prepayment is offered.');
        } elseif (str_starts_with($mollie, 'test_')) {
            $problems[] = __('Payment: Mollie runs with a test key.');
        }

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $problems[] = __('Email: mail is not delivered.');
        }

        if (config('app.debug')) {
            $problems[] = __('Server: APP_DEBUG is on.');
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $problems[] = __('Server: APP_URL is not https.');
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private static function pages(): array
    {
        $problems = [];
        $pages = Page::query()->where('is_published', true)->get()->keyBy('slug');

        foreach (['imprint', 'terms', 'withdrawal', 'privacy', 'shipping', 'accessibility'] as $slug) {
            $page = $pages->get($slug);

            if ($page === null) {
                $problems[] = __('Page ":slug" is missing or unpublished.', ['slug' => $slug]);

                continue;
            }

            // As shown to visitors in the language of whoever runs the check: placeholders filled from the settings.
            preg_match_all('/\[TODO: ([^\]]+)\]/', $page->renderedBody(), $matches);

            foreach (array_unique($matches[1]) as $todo) {
                $problems[] = __('Page ":page" has a gap: :todo is not set.', ['page' => $page->translate('title'), 'todo' => $todo]);
            }
        }

        return $problems;
    }
}
