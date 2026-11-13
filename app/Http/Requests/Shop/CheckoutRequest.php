<?php

namespace App\Http\Requests\Shop;

use App\Payments\PaymentManager;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $address = fn (string $prefix, bool $required) => [
            "{$prefix}.first_name" => [$required ? 'required' : 'nullable', 'string', 'max:100'],
            "{$prefix}.last_name" => [$required ? 'required' : 'nullable', 'string', 'max:100'],
            "{$prefix}.company" => ['nullable', 'string', 'max:150'],
            "{$prefix}.address1" => [$required ? 'required' : 'nullable', 'string', 'max:150'],
            "{$prefix}.address2" => ['nullable', 'string', 'max:150'],
            "{$prefix}.zip" => [$required ? 'required' : 'nullable', 'string', 'max:20'],
            "{$prefix}.city" => [$required ? 'required' : 'nullable', 'string', 'max:100'],
            "{$prefix}.country" => [$required ? 'required' : 'nullable', 'string', 'size:2', 'alpha'],
        ];

        return [
            'email' => ['required', 'email', 'max:255'],
            'ship_to_different' => ['boolean'],
            ...$address('billing_address', true),
            ...$address('shipping_address', $this->boolean('ship_to_different')),
            'vat_id' => ['nullable', 'string', 'max:20'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'gateway' => ['required', Rule::in(app(PaymentManager::class)->availableKeys())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'billing_address' => $this->upperCountry($this->input('billing_address')),
            'shipping_address' => $this->upperCountry($this->input('shipping_address')),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $address
     * @return array<string, mixed>|null
     */
    private function upperCountry(mixed $address): ?array
    {
        if (! is_array($address)) {
            return null;
        }

        if (isset($address['country']) && is_string($address['country'])) {
            $address['country'] = strtoupper($address['country']);
        }

        return $address;
    }

    /**
     * @return array<string, string|null>
     */
    public function shippingAddress(): array
    {
        return $this->boolean('ship_to_different')
            ? $this->validated('shipping_address')
            : $this->validated('billing_address');
    }
}
