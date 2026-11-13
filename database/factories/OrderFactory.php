<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\TaxTreatment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $address = [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company' => null,
            'address1' => fake()->streetAddress(),
            'address2' => null,
            'zip' => fake()->postcode(),
            'city' => fake()->city(),
            'country' => 'DE',
        ];

        return [
            'number' => 'B-'.Str::padLeft((string) fake()->unique()->numberBetween(1, 99999), 5, '0'),
            'user_id' => User::factory(),
            'status' => OrderStatus::Pending,
            'locale' => 'de',
            'currency' => 'EUR',
            'email' => fake()->safeEmail(),
            'customer_group_code' => 'b2c',
            'prices_include_tax' => true,
            'billing_address' => $address,
            'shipping_address' => $address,
            'country' => 'DE',
            'vat_id' => null,
            'vat_id_valid' => false,
            'tax_treatment' => TaxTreatment::Domestic,
            'tax_note' => null,
            'subtotal_net_cents' => 1000,
            'discount_cents' => 0,
            'shipping_net_cents' => 0,
            'shipping_tax_cents' => 0,
            'shipping_tax_rate' => 0,
            'tax_cents' => 70,
            'total_cents' => 1070,
            'tax_summary' => [['rate' => 7.0, 'category' => 'S', 'net' => 1000, 'tax' => 70, 'gross' => 1070]],
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => OrderStatus::Paid, 'paid_at' => now(), 'payment_gateway' => 'mollie']);
    }

    public function intraCommunity(): static
    {
        return $this->state(fn (array $attributes) => [
            'country' => 'AT',
            'billing_address' => [...$attributes['billing_address'], 'country' => 'AT', 'company' => 'Buchhandlung GmbH'],
            'shipping_address' => [...$attributes['shipping_address'], 'country' => 'AT'],
            'vat_id' => 'ATU12345678',
            'vat_id_valid' => true,
            'tax_treatment' => TaxTreatment::IntraCommunity,
            'tax_cents' => 0,
            'total_cents' => 1000,
            'tax_summary' => [['rate' => 0.0, 'category' => 'K', 'net' => 1000, 'tax' => 0, 'gross' => 1000]],
        ]);
    }
}
