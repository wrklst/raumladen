<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One shop setting made in the backend. The key is a config path
 * (e.g. "shop.seller.name"); see App\Settings\ShopSettings for what may be
 * stored and how it reaches config().
 *
 * @property string $key
 * @property string|null $value JSON, or an encrypted string for secrets
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;
}
