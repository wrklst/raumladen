<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;

/**
 * One anonymous storefront event (a page view, an order placed). Written by
 * App\Analytics\SiteVisits, read by the statistics page.
 *
 * @property string $type
 * @property string $path
 * @property string|null $locale
 * @property string $visit one tab's visit, from the browser's memory only
 * @property string $visit_key one browser for one day, not derivable afterwards
 * @property int|null $seconds
 * @property Carbon $created_at
 */
class SiteEvent extends Model
{
    use Prunable;

    public const int KEEP_MONTHS = 13;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * Statistics older than thirteen months are deleted (`model:prune`, daily).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subMonths(self::KEEP_MONTHS));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
