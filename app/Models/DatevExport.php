<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property int $invoice_count
 * @property int $booking_count
 * @property string $path
 * @property string $sha256
 * @property int|null $user_id
 */
#[Fillable(['from_date', 'to_date', 'invoice_count', 'booking_count', 'path', 'sha256', 'user_id'])]
class DatevExport extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
        ];
    }
}
