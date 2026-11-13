<?php

use App\Analytics\SiteVisits;
use App\Enums\UserRole;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\SiteEvent;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

const BROWSER = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';

function beacon(array $events, array $overrides = [], string $userAgent = BROWSER, string $ip = '203.0.113.7')
{
    $body = json_encode([
        'visit' => 'abcdefgh12345678',
        'locale' => 'de',
        'landing' => ['path' => '/de', 'referrer' => 'https://www.example.org/archive/1', 'utm_source' => 'newsletter', 'utm_campaign' => 'herbst'],
        'events' => $events,
        ...$overrides,
    ]);

    return test()->call('POST', '/site-events', server: ['HTTP_USER_AGENT' => $userAgent, 'REMOTE_ADDR' => $ip, 'CONTENT_TYPE' => 'text/plain'], content: $body);
}

test('page views are stored without anything that identifies the visitor', function () {
    $response = beacon([
        ['type' => 'pageview', 'path' => '/de', 'seconds' => 12, 'ago' => 40],
        ['type' => 'pageview', 'path' => '/de/products/the-morning-of-space?utm_source=x#top', 'seconds' => 95, 'ago' => 5],
    ])->assertNoContent();

    // The door sets no cookie and starts no session.
    expect($response->headers->getCookies())->toBe([]);

    $events = SiteEvent::query()->orderBy('id')->get();

    expect($events)->toHaveCount(2)
        ->and($events[1]->only(['type', 'path', 'locale', 'visit', 'landing_path', 'referrer_host', 'utm_source', 'utm_campaign', 'device', 'seconds']))->toBe([
            'type' => 'pageview', 'path' => '/de/products/the-morning-of-space', 'locale' => 'de', 'visit' => 'abcdefgh12345678',
            'landing_path' => '/de', 'referrer_host' => 'example.org', 'utm_source' => 'newsletter', 'utm_campaign' => 'herbst',
            'device' => 'desktop', 'seconds' => 95,
        ])
        ->and($events[0]->created_at->diffInSeconds($events[1]->created_at))->toEqualWithDelta(35, 1);

    // Neither the address nor the browser string exists anywhere in the table.
    $stored = json_encode(SiteEvent::query()->get()->map->getAttributes());

    expect($stored)->not->toContain('203.0.113.7')->not->toContain('Safari')->not->toContain('Mozilla')
        ->and(Schema::getColumnListing('site_events'))->not->toContain('ip', 'ip_address', 'user_agent');
});

test('a visitor has one key for a day and another one the next day', function () {
    $today = SiteVisits::dailyKey('203.0.113.7', BROWSER);

    expect(SiteVisits::dailyKey('203.0.113.7', BROWSER))->toBe($today)
        ->and(SiteVisits::dailyKey('203.0.113.8', BROWSER))->not->toBe($today)
        ->and($today)->toHaveLength(32)->not->toContain('203');

    $this->travel(1)->days();

    expect(SiteVisits::dailyKey('203.0.113.7', BROWSER))->not->toBe($today);
});

test('ids of a person\'s order never end up in a path', function () {
    beacon([
        ['type' => 'pageview', 'path' => '/de/orders/0193a5b2-7c1e-7d3a-9f00-aa11bb22cc33/complete'],
        ['type' => 'pageview', 'path' => '/de/account/orders/0193a5b2-7c1e-7d3a-9f00-aa11bb22cc33/invoices/17'],
    ]);

    expect(SiteEvent::query()->orderBy('id')->pluck('path')->all())
        ->toBe(['/de/orders/:id/complete', '/de/account/orders/:id/invoices/:id']);
});

test('bots, malformed bodies and unknown event types are dropped quietly', function () {
    beacon([['type' => 'pageview', 'path' => '/de']], userAgent: 'Mozilla/5.0 (compatible; Googlebot/2.1)')->assertNoContent();
    beacon([['type' => 'pageview', 'path' => '/de']], userAgent: '')->assertNoContent();
    beacon([['type' => 'pageview', 'path' => '/de']], ['visit' => 'short'])->assertNoContent();
    beacon([['type' => 'purchase', 'path' => '/de'], ['type' => 'pageview', 'path' => 'javascript:alert(1)'], 'nonsense'])->assertNoContent();
    $this->call('POST', '/site-events', server: ['HTTP_USER_AGENT' => BROWSER], content: 'not json')->assertNoContent();
    $this->call('POST', '/site-events', server: ['HTTP_USER_AGENT' => BROWSER], content: str_repeat('x', 40000))->assertNoContent();

    expect(SiteEvent::query()->count())->toBe(0);
});

test('nothing is counted while statistics are switched off, and the page says so to the script', function () {
    CustomerGroup::factory()->consumer()->create();

    expect($this->get('/de')->getContent())->toContain('<meta name="site-statistics" content="on">');

    config(['shop.analytics.enabled' => false]);

    beacon([['type' => 'pageview', 'path' => '/de']])->assertNoContent();

    expect(SiteEvent::query()->count())->toBe(0)
        ->and($this->get('/de')->getContent())->not->toContain('site-statistics');
});

test('the backend itself is not part of the statistics', function () {
    CustomerGroup::factory()->consumer()->create();

    $html = $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->getContent();

    expect($html)->not->toContain('site-statistics');
});

test('old statistics are deleted after thirteen months', function () {
    beacon([['type' => 'pageview', 'path' => '/de']]);
    SiteEvent::query()->update(['created_at' => now()->subMonths(14)]);
    beacon([['type' => 'pageview', 'path' => '/en']]);

    $this->artisan('model:prune', ['--model' => [SiteEvent::class]])->assertSuccessful();

    expect(SiteEvent::query()->pluck('path')->all())->toBe(['/en']);
});

test('the report counts visits, pages, sources and the way to an order', function () {
    CustomerGroup::factory()->consumer()->create();

    // Visit one: from the newsletter to checkout.
    beacon([
        ['type' => 'pageview', 'path' => '/de', 'seconds' => 10],
        ['type' => 'pageview', 'path' => '/de/products/the-morning-of-space', 'seconds' => 50],
        ['type' => 'pageview', 'path' => '/de/cart', 'seconds' => 5],
        ['type' => 'pageview', 'path' => '/de/checkout', 'seconds' => 55],
    ]);
    // Visit two: a phone, no referrer, one page.
    beacon([['type' => 'pageview', 'path' => '/en', 'seconds' => 20]], ['visit' => 'zzzzzzzz99999999', 'locale' => 'en', 'landing' => ['path' => '/en']], 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148', '198.51.100.9');
    Order::factory()->create();

    $this->actingAs(User::factory()->create(['role' => UserRole::Manager]))
        ->get(route('admin.statistics', ['days' => 7]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/statistics')
            ->where('days', 7)
            ->where('totals', ['visits' => 2, 'visitors' => 2, 'pageviews' => 5, 'seconds_per_visit' => 70])
            ->where('funnel', ['visits' => 2, 'product' => 1, 'cart' => 1, 'checkout' => 1, 'orders' => 1])
            ->where('referrers', [['name' => 'example.org', 'count' => 1]])
            ->where('campaigns', [['name' => 'herbst', 'count' => 1]])
            ->where('devices', [['name' => 'desktop', 'count' => 1], ['name' => 'phone', 'count' => 1]])
            ->where('locales', [['name' => 'de', 'count' => 1], ['name' => 'en', 'count' => 1]])
            ->where('pages.0.count', 1)
            ->has('by_day', 7)
            ->where('by_day.6.visits', 2));

    // Statistics are for administrators and shop managers.
    $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))->get(route('admin.statistics'))->assertForbidden();
});
