<?php

namespace App\Tax;

use App\Enums\OrderStatus;
use App\Enums\TaxTreatment;
use App\Models\Order;
use App\Support\Countries;
use Illuminate\Support\Facades\Log;

/**
 * Decides the VAT treatment of a sale from the seller's perspective (German e.V.).
 */
class TaxResolver
{
    public function resolve(string $destination, bool $vatIdValid, bool $digital = false, int $thisSaleNetCents = 0): TaxTreatment
    {
        $destination = strtoupper($destination);
        $home = config('shop.tax.home_country');

        if (config('shop.tax.small_business')) {
            return TaxTreatment::SmallBusiness;
        }

        if ($destination === $home) {
            return TaxTreatment::Domestic;
        }

        if (! Countries::isEu($destination)) {
            // ponytail: digital B2C to third countries may owe destination VAT; books are physical for now.
            return TaxTreatment::Export;
        }

        if ($vatIdValid) {
            return $digital ? TaxTreatment::ReverseCharge : TaxTreatment::IntraCommunity;
        }

        if (config('shop.tax.oss_registered')) {
            return TaxTreatment::Oss;
        }

        if ($this->crossesDistanceThreshold($thisSaleNetCents)) {
            Log::warning('EU distance-selling threshold crossed without OSS registration; destination VAT applied.', [
                'destination' => $destination,
            ]);

            return TaxTreatment::Oss;
        }

        return TaxTreatment::DistanceHome;
    }

    /**
     * Art. 59c MwStSystRL: EUR 10,000 net of EU B2C distance sales in the previous
     * or the current calendar year, including the sale that crosses the line.
     */
    public function crossesDistanceThreshold(int $thisSaleNetCents): bool
    {
        $limit = (int) config('shop.tax.distance_threshold_cents');

        return $this->distanceSalesNet(now()->subYear()->year) > $limit
            || $this->distanceSalesNet(now()->year) + $thisSaleNetCents > $limit;
    }

    public function distanceSalesNet(int $year): int
    {
        return (int) Order::query()
            ->whereIn('tax_treatment', [TaxTreatment::DistanceHome, TaxTreatment::Oss])
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereYear('created_at', $year)
            ->sum('subtotal_net_cents');
    }
}
