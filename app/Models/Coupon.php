<?php

namespace App\Models;

use App\Enums\CouponType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property CouponType $type
 * @property int $value
 * @property int $min_order_cents
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property int|null $max_uses
 * @property int $uses
 * @property int|null $customer_group_id
 * @property bool $is_active
 */
#[Fillable([
    'code', 'type', 'value', 'min_order_cents', 'starts_at', 'expires_at',
    'max_uses', 'customer_group_id', 'is_active',
])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    public static function findByCode(string $code): ?self
    {
        return self::query()->whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))])->first();
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /**
     * Why the coupon cannot be used, or null when it can.
     */
    public function rejectionReason(int $subtotalGrossCents, ?CustomerGroup $group): ?string
    {
        return match (true) {
            ! $this->is_active => 'inactive',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'not_started',
            $this->expires_at !== null && $this->expires_at->isPast() => 'expired',
            $this->max_uses !== null && $this->uses >= $this->max_uses => 'exhausted',
            $this->customer_group_id !== null && $this->customer_group_id !== $group?->id => 'wrong_group',
            $subtotalGrossCents < $this->min_order_cents => 'below_minimum',
            default => null,
        };
    }

    /**
     * Discount in cents on a gross subtotal, never more than the subtotal.
     */
    public function discountFor(int $subtotalGrossCents): int
    {
        $discount = match ($this->type) {
            CouponType::Percent => (int) round($subtotalGrossCents * $this->value / 100),
            CouponType::Fixed => $this->value,
        };

        return min($discount, $subtotalGrossCents);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
