<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SiteEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visitor statistics from the anonymous page views (App\Analytics\SiteVisits).
 */
class StatisticsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $days = in_array($request->integer('days'), [7, 30, 90, 365], true) ? $request->integer('days') : 30;
        $from = now()->subDays($days - 1)->startOfDay();

        // ponytail: aggregated in PHP from the period's rows; move the sums into SQL when a period holds millions of rows.
        $events = SiteEvent::query()->where('created_at', '>=', $from)->where('type', 'pageview')
            ->get(['path', 'locale', 'visit', 'visit_key', 'landing_path', 'referrer_host', 'utm_source', 'utm_campaign', 'device', 'seconds', 'created_at']);

        $visits = $events->groupBy('visit');
        $reached = fn (string $pattern): int => $visits->filter(fn (Collection $pages) => $pages->contains(fn (SiteEvent $event) => (bool) preg_match($pattern, $event->path)))->count();

        return Inertia::render('admin/statistics', [
            'days' => $days,
            'enabled' => (bool) config('shop.analytics.enabled'),
            'totals' => [
                'visits' => $visits->count(),
                'visitors' => $events->map(fn (SiteEvent $event) => $event->created_at->toDateString().$event->visit_key)->unique()->count(),
                'pageviews' => $events->count(),
                'seconds_per_visit' => $visits->isEmpty() ? 0 : (int) round($events->sum('seconds') / $visits->count()),
            ],
            'by_day' => collect(range($days - 1, 0))->map(function (int $ago) use ($events): array {
                $day = now()->subDays($ago)->toDateString();
                $ofDay = $events->filter(fn (SiteEvent $event) => $event->created_at->toDateString() === $day);

                return ['day' => $day, 'visits' => $ofDay->unique('visit')->count(), 'pageviews' => $ofDay->count()];
            })->all(),
            'pages' => $this->top($events->countBy('path')),
            // A visit has one source: counted once, from its first page view.
            'referrers' => $this->top($visits->map(fn (Collection $pages) => $pages->first()->referrer_host)->filter()->countBy()),
            'campaigns' => $this->top($visits->map(fn (Collection $pages) => $pages->first()->utm_campaign ?? $pages->first()->utm_source)->filter()->countBy()),
            'landing_pages' => $this->top($visits->map(fn (Collection $pages) => $pages->first()->landing_path)->filter()->countBy()),
            'devices' => $this->top($visits->map(fn (Collection $pages) => $pages->first()->device)->countBy()),
            'locales' => $this->top($visits->map(fn (Collection $pages) => $pages->first()->locale)->filter()->countBy()),
            'funnel' => [
                'visits' => $visits->count(),
                'product' => $reached('#^/[a-z]{2}/products/#'),
                'cart' => $reached('#^/[a-z]{2}/cart$#'),
                'checkout' => $reached('#^/[a-z]{2}/checkout$#'),
                // Orders are counted from the orders themselves: exact, and independent of who is counted as a visitor.
                'orders' => Order::query()->where('created_at', '>=', $from)->count(),
            ],
        ]);
    }

    /**
     * @param  Collection<array-key, int>  $counts
     * @return array<int, array{name: string, count: int}>
     */
    private function top(Collection $counts): array
    {
        return $counts->sortDesc()->take(10)->map(fn (int $count, int|string $name) => ['name' => (string) $name, 'count' => $count])->values()->all();
    }
}
