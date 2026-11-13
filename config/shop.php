<?php

use App\Payments\BankTransferGateway;
use App\Payments\MollieGateway;

return [

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

    'currency' => 'EUR',

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
        'name' => env('SHOP_SELLER_NAME', 'Beispielverlag e.V.'),
        'address1' => env('SHOP_SELLER_ADDRESS1', 'Musterstraße 1'),
        'zip' => env('SHOP_SELLER_ZIP', '10115'),
        'city' => env('SHOP_SELLER_CITY', 'Berlin'),
        'country' => 'DE',
        'email' => env('SHOP_SELLER_EMAIL', 'shop@example.org'),
        'phone' => env('SHOP_SELLER_PHONE', ''),
        'vat_id' => env('SHOP_SELLER_VAT_ID', ''),
        'tax_number' => env('SHOP_SELLER_TAX_NUMBER', ''),
        'register' => env('SHOP_SELLER_REGISTER', 'VR 00000 B, Amtsgericht Charlottenburg'),
        'representatives' => env('SHOP_SELLER_REPRESENTATIVES', ''),
        'iban' => env('SHOP_SELLER_IBAN', ''),
        'bic' => env('SHOP_SELLER_BIC', ''),
        'bank' => env('SHOP_SELLER_BANK', ''),
    ],

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
        'small_business' => (bool) env('SHOP_TAX_SMALL_BUSINESS', false),
        'oss_registered' => (bool) env('SHOP_TAX_OSS_REGISTERED', false),
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
    | at the rate of the order's largest line (Nebenleistung).
    |
    */

    'shipping' => [
        ['code' => 'DE', 'countries' => ['DE'], 'price_cents' => 390, 'free_from_cents' => 5000],
        ['code' => 'EU', 'countries' => 'eu', 'price_cents' => 990, 'free_from_cents' => 10000],
        ['code' => 'WORLD', 'countries' => '*', 'price_cents' => 1990, 'free_from_cents' => null],
    ],

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
            'api_key' => env('MOLLIE_KEY'),
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
        'consultant_number' => env('DATEV_CONSULTANT_NUMBER', ''),
        'client_number' => env('DATEV_CLIENT_NUMBER', ''),
        'skr' => env('DATEV_SKR', '04'),
        'account_length' => 4,
        'fiscal_year_start_month' => 1,
        'festschreibung' => true,
        'debtor_account' => env('DATEV_DEBTOR_ACCOUNT', '10000'),
        'accounts' => [
            'domestic_19' => env('DATEV_ACCOUNT_19', '4400'),
            'domestic_7' => env('DATEV_ACCOUNT_7', '4300'),
            'domestic_0' => env('DATEV_ACCOUNT_0', '4200'),
            'intra_community' => env('DATEV_ACCOUNT_IC', '4125'),
            'export' => env('DATEV_ACCOUNT_EXPORT', '4120'),
            'oss' => env('DATEV_ACCOUNT_OSS', '4320'),
            'reverse_charge' => env('DATEV_ACCOUNT_RC', '4336'),
            'small_business' => env('DATEV_ACCOUNT_SMALL', '4185'),
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
