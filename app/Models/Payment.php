<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string $gateway
 * @property string|null $gateway_id
 * @property PaymentStatus $status
 * @property int $amount_cents
 * @property int $refunded_cents
 * @property string $currency
 * @property string|null $method
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $paid_at
 */
#[Fillable([
    'order_id', 'gateway', 'gateway_id', 'status', 'amount_cents', 'refunded_cents',
    'currency', 'method', 'payload', 'paid_at',
])]
class Payment extends Model
{
    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }
}
