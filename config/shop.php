<?php

use App\Payments\BankTransferGateway;
use App\Payments\MollieGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Where these values come from
    |--------------------------------------------------------------------------
    |
    | This file holds the defaults. Everything a shop owner decides (seller,
    | payment, mail, shipping, tax, DATEV, channels, hosting) is set in the
    | backend under Shop settings and stored in the database; those values
    | replace the ones here when the application boots (App\Settings\
    | ShopSettings). The environment only carries what the server needs to
    | start: app key and URL, database, cache, session, queue, and the theme.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | To add a language: append its code here and create lang/{code}.json.
    | The first entry is the default storefront locale.
    |
    */

    'locales' => ['de', 'en'],

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | How the storefront, the invoices and the emails look (App\Theme\Theme).
    | The name of an installed theme package, e.g.
    | "acme/raumladen-theme-example", or a path; empty for the
    | built-in theme. This is the one design decision that lives in the
    | environment, because the front end is compiled with it: changing the
    | theme means `composer require`, a build and a deploy.
    |
    */

    'theme' => env('SHOP_THEME'),

    'currency' => 'EUR',

    /*
    |--------------------------------------------------------------------------
    | Site
    |--------------------------------------------------------------------------
    |
    | The public face of the shop, used in JSON-LD and llms.txt. The site name
    | itself is APP_NAME. The seller below is the legal entity.
    |
    */

    'site' => [
        // Per language: how the shop describes itself to search engines and AI
        // assistants. Empty: "Books from <seller>. Order online."
        'description' => [],
        // Profiles and websites that belong to the shop (JSON-LD sameAs).
        'same_as' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Seller (the publisher, a German e.V.)
    |--------------------------------------------------------------------------
    |
    | Printed on invoices, in the imprint and in JSON-LD. The register entry
    | and representatives are mandatory imprint content for an e.V. (§ 5 DDG).
    |
    */

    'seller' => [
        'name' => 'Beispielverlag e.V.',
        'address1' => 'Musterstraße 1',
        'zip' => '10115',
        'city' => 'Berlin',
        'country' => 'DE',
        'email' => 'shop@example.org',
        'phone' => '',
        'vat_id' => '',
        'tax_number' => '',
        'register' => '',
        'representatives' => '',
        'iban' => '',
        'bic' => '',
        'bank' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Processor and carrier
    |--------------------------------------------------------------------------
    |
    | Named in the imprint and the privacy policy: who hosts the shop and
    | sends its email on the seller's behalf, and who delivers the parcels.
    |
    */

    'processor' => [
        'name' => '',
        'address' => '',
        'location' => '',
        'log_retention' => '',
    ],

    'carrier' => '',

    /*
    |--------------------------------------------------------------------------
    | Tax
    |--------------------------------------------------------------------------
    |
    | Product prices are stored gross including German VAT of the product's
    | tax class. Net prices are derived from that and the destination rate is
    | applied on top (OSS / EU B2B / export).
    |
    | small_business: § 19 UStG, no VAT at all.
    | oss_registered: destination-country VAT for all EU B2C sales. When false,
    | the EU-wide EUR 10,000 distance-selling threshold decides.
    |
    | Rates: standard and the reduced rate that applies to printed books.
    | ponytail: static table, verify against the EU TEDB before go-live and
    | whenever a member state changes rates.
    |
    */

    'tax' => [
        'home_country' => 'DE',
        'small_business' => false,
        'oss_registered' => false,
        'distance_threshold_cents' => 1_000_000,
        'rates' => [
            'AT' => ['standard' => 20, 'reduced' => 10],
            'BE' => ['standard' => 21, 'reduced' => 6],
            'BG' => ['standard' => 20, 'reduced' => 9],
            'HR' => ['standard' => 25, 'reduced' => 5],
            'CY' => ['standard' => 19, 'reduced' => 5],
            'CZ' => ['standard' => 21, 'reduced' => 0],
            'DK' => ['standard' => 25, 'reduced' => 25],
            'EE' => ['standard' => 24, 'reduced' => 9],
            'FI' => ['standard' => 25.5, 'reduced' => 14],
            'FR' => ['standard' => 20, 'reduced' => 5.5],
            'DE' => ['standard' => 19, 'reduced' => 7],
            'GR' => ['standard' => 24, 'reduced' => 6],
            'HU' => ['standard' => 27, 'reduced' => 5],
            'IE' => ['standard' => 23, 'reduced' => 0],
            'IT' => ['standard' => 22, 'reduced' => 4],
            'LV' => ['standard' => 21, 'reduced' => 5],
            'LT' => ['standard' => 21, 'reduced' => 9],
            'LU' => ['standard' => 17, 'reduced' => 3],
            'MT' => ['standard' => 18, 'reduced' => 5],
            'NL' => ['standard' => 21, 'reduced' => 9],
            'PL' => ['standard' => 23, 'reduced' => 5],
            'PT' => ['standard' => 23, 'reduced' => 6],
            'RO' => ['standard' => 21, 'reduced' => 11],
            'SK' => ['standard' => 23, 'reduced' => 5],
            'SI' => ['standard' => 22, 'reduced' => 5],
            'ES' => ['standard' => 21, 'reduced' => 4],
            'SE' => ['standard' => 25, 'reduced' => 6],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping
    |--------------------------------------------------------------------------
    |
    | Zones are matched top to bottom. "eu" matches every EU member state,
    | "*" matches the rest of the world. Prices are gross; shipping is taxed
    | at the rate of the order's largest line (Nebenleistung). delivery_days
    | (min, max working days) is shown on product pages and in JSON-LD; it is
    | a promise to the customer, so only set what you can keep.
    |
    */

    'shipping' => [
        ['code' => 'DE', 'countries' => ['DE'], 'price_cents' => 390, 'free_from_cents' => 5000, 'delivery_days' => [2, 4]],
        ['code' => 'EU', 'countries' => 'eu', 'price_cents' => 990, 'free_from_cents' => 10000],
        ['code' => 'WORLD', 'countries' => '*', 'price_cents' => 1990, 'free_from_cents' => null],
    ],

    /*
    |--------------------------------------------------------------------------
    | Returns
    |--------------------------------------------------------------------------
    |
    | Statutory withdrawal period, published as MerchantReturnPolicy in
    | JSON-LD. Must match the withdrawal page.
    |
    */

    'returns' => [
        'days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fixed book prices
    |--------------------------------------------------------------------------
    |
    | Destinations where books must be sold to end customers at the fixed
    | price (Germany: BuchPrG; Austria: Buchpreisbindungsgesetz). Coupons do
    | not apply to consumer orders shipped there. Trade customers (net price
    | groups) are not affected.
    |
    */

    'fixed_price_countries' => ['DE', 'AT'],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | Up to this many visible issues the storefront downloads the catalogue
    | once (about 1.5 KB per issue before compression, cached by the browser)
    | and searches it locally, without a request per keystroke. Above it the
    | server answers each search from its cached index.
    |
    */

    'search' => [
        'browser_index_max' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Channels and search engines
    |--------------------------------------------------------------------------
    |
    | Product feeds for Google Merchant Center and for Meta (Facebook and
    | Instagram shops read the same feed format), the verification codes both
    | ask for, and whether search engines and AI crawlers may read the shop.
    | All of it is set in the backend under Shop settings.
    |
    */

    'channels' => [
        'google_feed' => true,
        'meta_feed' => false,
        'google_verification' => '',
        'meta_verification' => '',
    ],

    /*
    | General appearance, set in the backend; themes decide how it is shown.
    */
    'appearance' => [
        'logo' => null,
        'icon' => null,
        'invoice_note' => '',
        'mail_signature' => '',
    ],

    /*
    | Anonymous visitor statistics without cookies (App\Analytics\SiteVisits).
    */
    'analytics' => [
        'enabled' => true,
    ],

    'seo' => [
        // Off while the shop is being set up: robots.txt and every page then say "do not index".
        'indexable' => true,
        'llms_txt' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    |
    | Pending orders older than this many days are cancelled by
    | orders:cancel-unpaid so their stock is released again.
    |
    */

    'orders' => ['unpaid_cancel_days' => 14],

    /*
    |--------------------------------------------------------------------------
    | Document numbers
    |--------------------------------------------------------------------------
    |
    | Gapless, continuous sequences claimed inside the issuing transaction.
    |
    */

    'numbers' => [
        'order' => ['prefix' => 'B-', 'digits' => 5],
        'invoice' => ['prefix' => 'RE-', 'digits' => 5],
        'credit_note' => ['prefix' => 'GS-', 'digits' => 5],
    ],

    'invoice' => [
        'due_days' => 14,
        'disk' => 'local',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | Register additional gateways by key. Each must implement
    | App\Payments\PaymentGateway.
    |
    */

    'payments' => [
        'gateways' => [
            'mollie' => MollieGateway::class,
            'bank_transfer' => BankTransferGateway::class,
        ],
        'mollie' => [
            'api_key' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | DATEV
    |--------------------------------------------------------------------------
    |
    | EXTF Buchungsstapel export. Account defaults follow SKR04 and use
    | automatic accounts (no BU key). Let the tax advisor confirm them.
    |
    */

    'datev' => [
        'consultant_number' => '',
        'client_number' => '',
        'skr' => '04',
        'account_length' => 4,
        'fiscal_year_start_month' => 1,
        'festschreibung' => true,
        'debtor_account' => '10000',
        'accounts' => [
            'domestic_19' => '4400',
            'domestic_7' => '4300',
            'domestic_0' => '4200',
            'intra_community' => '4125',
            'export' => '4120',
            'oss' => '4320',
            'reverse_charge' => '4336',
            'small_business' => '4185',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer login codes
    |--------------------------------------------------------------------------
    */

    'login_code' => [
        'ttl_minutes' => 10,
        'length' => 6,
    ],

];
