<?php

namespace App\Tax;

class TaxMath
{
    /**
     * Split an amount into net, tax and gross at the given rate.
     *
     * @return array{net: int, tax: int, gross: int}
     */
    public static function split(int $amountCents, float $ratePercent, bool $amountIsGross): array
    {
        if ($amountIsGross) {
            $net = (int) round($amountCents / (1 + $ratePercent / 100));

            return ['net' => $net, 'tax' => $amountCents - $net, 'gross' => $amountCents];
        }

        $tax = (int) round($amountCents * $ratePercent / 100);

        return ['net' => $amountCents, 'tax' => $tax, 'gross' => $amountCents + $tax];
    }

    /**
     * Net amount hidden in a gross price at the given rate, unrounded.
     */
    public static function netOf(int $grossCents, float $ratePercent): float
    {
        return $grossCents / (1 + $ratePercent / 100);
    }

    /**
     * Distribute a total across weights proportionally so the parts sum exactly
     * to the total (largest remainder method).
     *
     * @param  array<int|string, int>  $weights
     * @return array<int|string, int>
     */
    public static function distribute(int $total, array $weights): array
    {
        $sum = array_sum($weights);

        if ($sum <= 0 || $total === 0) {
            return array_map(fn () => 0, $weights);
        }

        $parts = [];
        $remainders = [];

        foreach ($weights as $key => $weight) {
            $exact = $total * $weight / $sum;
            $parts[$key] = (int) floor($exact);
            $remainders[$key] = $exact - $parts[$key];
        }

        arsort($remainders);
        $missing = $total - array_sum($parts);

        foreach (array_keys($remainders) as $key) {
            if ($missing <= 0) {
                break;
            }

            $parts[$key]++;
            $missing--;
        }

        return $parts;
    }
}
