<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Gapless counters. claim() must run inside the transaction that persists
 * the numbered record so a failed insert releases the number again.
 *
 * @property string $key
 * @property int $next_number
 */
#[Fillable(['key', 'next_number'])]
class NumberSequence extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public static function claim(string $key): int
    {
        $row = self::query()->lockForUpdate()->find($key);

        if ($row === null) {
            self::query()->insertOrIgnore(['key' => $key, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $row = self::query()->lockForUpdate()->findOrFail($key);
        }

        $number = $row->next_number;

        self::query()->whereKey($key)->update(['next_number' => $number + 1, 'updated_at' => now()]);

        return $number;
    }

    /**
     * Claim and format the next number for a configured sequence (see config/shop.php).
     */
    public static function nextFormatted(string $sequence): string
    {
        $format = config("shop.numbers.{$sequence}");

        return $format['prefix'].str_pad((string) self::claim($sequence), $format['digits'], '0', STR_PAD_LEFT);
    }
}
