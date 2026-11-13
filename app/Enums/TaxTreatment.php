<?php

namespace App\Enums;

/**
 * Where and how VAT applies to an order. Decided once per order by TaxResolver.
 */
enum TaxTreatment: string
{
    /** Destination is the home country: home rate. */
    case Domestic = 'domestic';

    /** EU B2C below the EUR 10,000 distance-selling threshold: home rate. */
    case DistanceHome = 'distance_home';

    /** EU B2C above threshold or OSS registered: destination rate. */
    case Oss = 'oss';

    /** EU B2B goods with a verified VAT ID: tax free, § 4 Nr. 1b UStG. */
    case IntraCommunity = 'intra_community';

    /** EU B2B services with a verified VAT ID: buyer owes the tax, § 13b. */
    case ReverseCharge = 'reverse_charge';

    /** Third country: tax free export, § 4 Nr. 1a UStG. */
    case Export = 'export';

    /** Seller is a small business, § 19 UStG: no VAT at all. */
    case SmallBusiness = 'small_business';

    /**
     * EN 16931 / UNTDID 5305 tax category code.
     */
    public function category(): string
    {
        return match ($this) {
            self::Domestic, self::DistanceHome, self::Oss => 'S',
            self::IntraCommunity => 'K',
            self::Export => 'G',
            self::ReverseCharge => 'AE',
            self::SmallBusiness => 'E',
        };
    }

    public function isTaxed(): bool
    {
        return $this->category() === 'S';
    }

    /**
     * Country whose rates apply, or null when no tax is charged.
     */
    public function rateCountry(string $destination): ?string
    {
        return match ($this) {
            self::Domestic, self::DistanceHome => config('shop.tax.home_country'),
            self::Oss => $destination,
            default => null,
        };
    }

    /**
     * Legal note printed on the invoice, translated into the order locale.
     */
    public function note(string $locale): ?string
    {
        $key = match ($this) {
            self::IntraCommunity => 'tax.note.intra_community',
            self::ReverseCharge => 'tax.note.reverse_charge',
            self::Export => 'tax.note.export',
            self::SmallBusiness => 'tax.note.small_business',
            default => null,
        };

        if ($key === null) {
            return null;
        }

        $note = __($key, [], $locale);

        return is_string($note) ? $note : null;
    }
}
