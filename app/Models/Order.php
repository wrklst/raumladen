<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\TaxTreatment;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $number
 * @property int|null $user_id
 * @property OrderStatus $status
 * @property string $locale
 * @property string $currency
 * @property string $email
 * @property string $customer_group_code
 * @property bool $prices_include_tax
 * @property array<string, string|null> $billing_address
 * @property array<string, string|null> $shipping_address
 * @property string $country
 * @property string|null $vat_id
 * @property bool $vat_id_valid
 * @property TaxTreatment $tax_treatment
 * @property string|null $tax_note
 * @property int|null $coupon_id
 * @property string|null $coupon_code
 * @property int $subtotal_net_cents
 * @property int $discount_cents
 * @property int $shipping_net_cents
 * @property int $shipping_tax_cents
 * @property float $shipping_tax_rate
 * @property int $tax_cents
 * @property int $total_cents
 * @property array<int, array{rate: float, category: string, net: int, tax: int, gross: int}>|null $tax_summary
 * @property string|null $payment_gateway
 * @property Carbon|null $paid_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $cancelled_at
 * @property string|null $customer_note
 */
#[Fillable([
    'number', 'user_id', 'status', 'locale', 'currency', 'email', 'customer_group_code', 'prices_include_tax',
    'billing_address', 'shipping_address', 'country', 'vat_id', 'vat_id_valid', 'tax_treatment', 'tax_note',
    'coupon_id', 'coupon_code', 'subtotal_net_cents', 'discount_cents', 'shipping_net_cents', 'shipping_tax_cents',
    'shipping_tax_rate', 'tax_cents', 'total_cents', 'tax_summary', 'payment_gateway', 'paid_at', 'shipped_at',
    'cancelled_at', 'customer_note',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $order->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function isOpen(): bool
    {
        return $this->status === OrderStatus::Pending;
    }

    public function customerName(): string
    {
        return trim(($this->billing_address['first_name'] ?? '').' '.($this->billing_address['last_name'] ?? ''));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'tax_treatment' => TaxTreatment::class,
            'prices_include_tax' => 'boolean',
            'vat_id_valid' => 'boolean',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'tax_summary' => 'array',
            'shipping_tax_rate' => 'float',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
