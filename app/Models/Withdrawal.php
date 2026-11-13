<?php

namespace App\Models;

use Database\Factories\WithdrawalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A consumer's declaration of withdrawal made through the online function
 * (§ 356a BGB). Kept as received: it is evidence of the date and content.
 *
 * @property int $id
 * @property int|null $order_id
 * @property string $order_number
 * @property string $name
 * @property string $email
 * @property string|null $statement
 * @property string $locale
 * @property Carbon $created_at
 */
#[Fillable(['order_id', 'order_number', 'name', 'email', 'statement', 'locale'])]
class Withdrawal extends Model
{
    /** @use HasFactory<WithdrawalFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
