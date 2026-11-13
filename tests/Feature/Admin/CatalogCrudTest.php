<?php

use App\Enums\CouponType;
use App\Enums\TaxClass;
use App\Models\Coupon;
use App\Models\CustomerGroup;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->consumer = CustomerGroup::factory()->consumer()->create();
    $this->actingAs(User::factory()->admin()->create());
});

function productPayload(array $overrides = []): array
{
    return [
        'type' => 'book',
        'title' => ['de' => 'Neues Buch', 'en' => 'New Book'],
        'tax_class' => 'reduced',
        'price' => '12.90',
        'stock' => 5,
        'track_stock' => 1,
        'is_active' => 1,
        ...$overrides,
    ];
}

// Products

test('an admin creates a product with prices in euros and slugs derived from the titles', function () {
    $this->post(route('admin.products.store'), productPayload(['sku' => 'BK-9', 'weight_grams' => 300, 'specs' => ['isbn' => '9783161484100', 'authors' => '']]))
        ->assertRedirect();

    $product = Product::query()->sole();

    expect($product->price_cents)->toBe(1290)
        ->and($product->sku)->toBe('BK-9')
        ->and($product->slug)->toBe(['de' => 'neues-buch', 'en' => 'new-book'])
        ->and($product->title)->toBe(['de' => 'Neues Buch', 'en' => 'New Book'])
        ->and($product->tax_class)->toBe(TaxClass::Reduced)
        ->and($product->stock)->toBe(5)
        ->and($product->track_stock)->toBeTrue()
        ->and($product->is_active)->toBeTrue()
        ->and($product->weight_grams)->toBe(300)
        ->and($product->specs)->toBe(['isbn' => '9783161484100']);
});

test('an explicit slug wins over the derived one and an empty sku becomes null', function () {
    $this->post(route('admin.products.store'), productPayload(['sku' => '', 'slug' => ['de' => 'mein-slug']]))->assertRedirect();

    $product = Product::query()->sole();

    expect($product->slug)->toBe(['de' => 'mein-slug', 'en' => 'new-book'])->and($product->sku)->toBeNull();
});

test('a product is validated', function (array $overrides, string $field) {
    $this->post(route('admin.products.store'), productPayload($overrides))->assertSessionHasErrors($field);

    expect(Product::query()->count())->toBe(0);
})->with([
    'default-locale title missing' => [['title' => ['en' => 'Only English']], 'title.de'],
    'unknown type' => [['type' => 'gadget'], 'type'],
    'unknown tax class' => [['tax_class' => 'luxury'], 'tax_class'],
    'price missing' => [['price' => ''], 'price'],
    'price not numeric' => [['price' => 'cheap'], 'price'],
    'price negative' => [['price' => '-1'], 'price'],
    'price too high' => [['price' => '100000'], 'price'],
    'negative stock' => [['stock' => -1], 'stock'],
    'fractional stock' => [['stock' => 1.5], 'stock'],
    'slug with uppercase' => [['slug' => ['de' => 'Bad Slug']], 'slug.de'],
    'group price negative' => [['group_prices' => [1 => '-5']], 'group_prices.1'],
]);

test('a sku must be unique, except for the product being edited', function () {
    $existing = Product::factory()->create(['sku' => 'BK-1']);

    $this->post(route('admin.products.store'), productPayload(['sku' => 'BK-1']))->assertSessionHasErrors('sku');

    $this->put(route('admin.products.update', $existing), productPayload(['sku' => 'BK-1', 'price' => '9.90']))
        ->assertSessionHasNoErrors();

    expect($existing->refresh()->price_cents)->toBe(990);
});

test('an admin updates a product', function () {
    $product = Product::factory()->create(['price_cents' => 1000, 'is_active' => true]);

    $this->put(route('admin.products.update', $product), productPayload(['title' => ['de' => 'Umbenannt'], 'price' => '19.99', 'tax_class' => 'standard', 'is_active' => 0, 'stock' => 0]))
        ->assertRedirect(route('admin.products.edit', $product));

    $product->refresh();

    expect($product->title)->toBe(['de' => 'Umbenannt'])
        ->and($product->price_cents)->toBe(1999)
        ->and($product->tax_class)->toBe(TaxClass::Standard)
        ->and($product->is_active)->toBeFalse()
        ->and($product->stock)->toBe(0);
});

test('group prices are created, updated and removed with the product', function () {
    $business = CustomerGroup::factory()->business()->create();
    $product = Product::factory()->create();

    $this->put(route('admin.products.update', $product), productPayload(['group_prices' => [$business->id => '15,50']]))->assertSessionHasErrors('group_prices.'.$business->id);

    $this->put(route('admin.products.update', $product), productPayload(['group_prices' => [$business->id => '15.50']]));
    expect(ProductPrice::query()->where('product_id', $product->id)->sole()->price_cents)->toBe(1550);

    $this->put(route('admin.products.update', $product), productPayload(['group_prices' => [$business->id => '14.00']]));
    expect(ProductPrice::query()->where('product_id', $product->id)->sole()->price_cents)->toBe(1400);

    $this->put(route('admin.products.update', $product), productPayload(['group_prices' => [$business->id => '']]));
    expect(ProductPrice::query()->where('product_id', $product->id)->count())->toBe(0);
});

test('uploaded images are stored and removed images are deleted from disk', function () {
    Storage::fake('public');
    $product = Product::factory()->create();

    $this->post(route('admin.products.update', $product), productPayload(['_method' => 'PUT', 'images' => [UploadedFile::fake()->image('cover.jpg')]]));

    $path = $product->refresh()->images[0];
    Storage::disk('public')->assertExists($path);

    $this->put(route('admin.products.update', $product), productPayload(['existing_images' => []]));

    Storage::disk('public')->assertMissing($path);
    expect($product->refresh()->images)->toBe([]);
})->skip(! extension_loaded('gd'), 'GD is required for fake images');

test('only images are accepted as product images', function () {
    Storage::fake('public');

    $this->post(route('admin.products.store'), productPayload(['images' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]]))
        ->assertSessionHasErrors('images.0');
});

test('deleting a product soft-deletes it', function () {
    $product = Product::factory()->create();

    $this->delete(route('admin.products.destroy', $product))->assertRedirect(route('admin.products.index'));

    expect($product->refresh()->trashed())->toBeTrue()->and(Product::query()->count())->toBe(0);
});

test('the product list can be searched by title or sku', function () {
    Product::factory()->create(['sku' => 'FIND-ME', 'title' => ['de' => 'Alpha']]);
    Product::factory()->create(['sku' => 'OTHER', 'title' => ['de' => 'Beta']]);

    $this->get(route('admin.products.index', ['q' => 'FIND']))
        ->assertInertia(fn ($page) => $page->has('products.data', 1)->where('products.data.0.sku', 'FIND-ME'));
});

// Coupons

function couponPayload(array $overrides = []): array
{
    return ['code' => 'spring10', 'type' => 'percent', 'value' => '15', 'is_active' => 1, ...$overrides];
}

test('an admin creates a percent coupon with an upper-cased code', function () {
    $this->post(route('admin.coupons.store'), couponPayload(['min_order' => '20', 'max_uses' => 10]))->assertRedirect(route('admin.coupons.index'));

    $coupon = Coupon::query()->sole();

    expect($coupon->code)->toBe('SPRING10')
        ->and($coupon->type)->toBe(CouponType::Percent)
        ->and($coupon->value)->toBe(15)
        ->and($coupon->min_order_cents)->toBe(2000)
        ->and($coupon->max_uses)->toBe(10)
        ->and($coupon->is_active)->toBeTrue()
        ->and($coupon->uses)->toBe(0);
});

test('a fixed coupon is entered in euros and stored in cents, and expires at the end of its day', function () {
    $this->post(route('admin.coupons.store'), couponPayload(['type' => 'fixed', 'value' => '5.50', 'starts_at' => '2026-01-01', 'expires_at' => '2026-12-31']))->assertRedirect();

    $coupon = Coupon::query()->sole();

    expect($coupon->value)->toBe(550)
        ->and($coupon->starts_at->toDateString())->toBe('2026-01-01')
        ->and($coupon->expires_at->format('Y-m-d H:i:s'))->toBe('2026-12-31 23:59:59');
});

test('a coupon is validated', function (array $overrides, string $field) {
    Coupon::factory()->create(['code' => 'TAKEN']);

    $this->post(route('admin.coupons.store'), couponPayload($overrides))->assertSessionHasErrors($field);

    expect(Coupon::query()->count())->toBe(1);
})->with([
    'percent above 100' => [['value' => '101'], 'value'],
    'negative value' => [['value' => '-1'], 'value'],
    'code already taken, ignoring case' => [['code' => 'TAKEN'], 'code'],
    'code with spaces' => [['code' => 'two words'], 'code'],
    'unknown type' => [['type' => 'bogo'], 'type'],
    'expires before it starts' => [['starts_at' => '2026-02-01', 'expires_at' => '2026-01-01'], 'expires_at'],
    'max uses zero' => [['max_uses' => 0], 'max_uses'],
    'unknown customer group' => [['customer_group_id' => 999], 'customer_group_id'],
    'negative minimum' => [['min_order' => '-1'], 'min_order'],
]);

test('a fixed coupon may exceed 100', function () {
    $this->post(route('admin.coupons.store'), couponPayload(['type' => 'fixed', 'value' => '150']))->assertSessionHasNoErrors();

    expect(Coupon::query()->sole()->value)->toBe(15000);
});

test('an admin updates a coupon and may keep its code', function () {
    $coupon = Coupon::factory()->create(['code' => 'KEEP', 'uses' => 4]);

    $this->put(route('admin.coupons.update', $coupon), couponPayload(['code' => 'keep', 'value' => '25', 'customer_group_id' => $this->consumer->id, 'is_active' => 0]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.coupons.index'));

    $coupon->refresh();

    expect($coupon->value)->toBe(25)
        ->and($coupon->customer_group_id)->toBe($this->consumer->id)
        ->and($coupon->is_active)->toBeFalse()
        ->and($coupon->uses)->toBe(4);
});

test('an admin deletes a coupon', function () {
    $coupon = Coupon::factory()->create();

    $this->delete(route('admin.coupons.destroy', $coupon))->assertRedirect(route('admin.coupons.index'));

    expect(Coupon::query()->count())->toBe(0);
});

// Pages

function pagePayload(array $overrides = []): array
{
    return ['slug' => 'Imprint', 'title' => ['de' => 'Impressum', 'en' => ''], 'body' => ['de' => '# Impressum', 'en' => ''], 'is_published' => 1, ...$overrides];
}

test('an admin creates a page with a lower-cased slug and without empty translations', function () {
    $this->post(route('admin.pages.store'), pagePayload())->assertRedirect(route('admin.pages.edit', 'imprint'));

    $page = Page::query()->sole();

    expect($page->slug)->toBe('imprint')
        ->and($page->title)->toBe(['de' => 'Impressum'])
        ->and($page->body)->toBe(['de' => '# Impressum'])
        ->and($page->is_published)->toBeTrue();
});

test('a page is validated', function (array $overrides, string $field) {
    Page::factory()->create(['slug' => 'taken']);

    $this->post(route('admin.pages.store'), pagePayload($overrides))->assertSessionHasErrors($field);

    expect(Page::query()->count())->toBe(1);
})->with([
    'title in the default locale missing' => [['title' => ['en' => 'Imprint']], 'title.de'],
    'title missing' => [['title' => null], 'title'],
    'slug with spaces' => [['slug' => 'two words'], 'slug'],
    'slug missing' => [['slug' => ''], 'slug'],
    'slug taken' => [['slug' => 'taken'], 'slug'],
    'body too long' => [['body' => ['de' => str_repeat('a', 100001)]], 'body.de'],
]);

test('an admin updates a page, keeping its own slug', function () {
    $page = Page::factory()->create(['slug' => 'terms', 'is_published' => true]);

    $this->put(route('admin.pages.update', $page), pagePayload(['slug' => 'terms', 'is_published' => 0]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.pages.edit', 'terms'));

    expect($page->refresh()->is_published)->toBeFalse()->and($page->title)->toBe(['de' => 'Impressum']);
});

test('an admin deletes a page', function () {
    $page = Page::factory()->create();

    $this->delete(route('admin.pages.destroy', $page))->assertRedirect(route('admin.pages.index'));

    expect(Page::query()->count())->toBe(0);
});

// Customer groups

function groupPayload(array $overrides = []): array
{
    return ['code' => 'Wholesale', 'name' => 'Wholesale', 'prices_include_tax' => 0, 'is_default' => 0, ...$overrides];
}

test('an admin creates a customer group with a lower-cased code', function () {
    $this->post(route('admin.customer-groups.store'), groupPayload())->assertRedirect();

    $group = CustomerGroup::query()->where('code', 'wholesale')->sole();

    expect($group->name)->toBe('Wholesale')->and($group->prices_include_tax)->toBeFalse()->and($group->is_default)->toBeFalse();
});

test('a customer group is validated', function (array $overrides, string $field) {
    $this->post(route('admin.customer-groups.store'), groupPayload($overrides))->assertSessionHasErrors($field);
})->with([
    'code taken' => [['code' => 'b2c'], 'code'],
    'code with spaces' => [['code' => 'two words'], 'code'],
    'code missing' => [['code' => ''], 'code'],
    'name missing' => [['name' => ''], 'name'],
    'code too long' => [['code' => str_repeat('a', 31)], 'code'],
]);

test('making a group the default demotes the previous default', function () {
    $this->post(route('admin.customer-groups.store'), groupPayload(['is_default' => 1]))->assertRedirect();

    expect(CustomerGroup::default()->code)->toBe('wholesale')
        ->and(CustomerGroup::query()->where('is_default', true)->count())->toBe(1);
});

test('editing the default group keeps it the default', function () {
    $this->put(route('admin.customer-groups.update', $this->consumer), groupPayload(['code' => 'b2c', 'name' => 'Consumers', 'prices_include_tax' => 1, 'is_default' => 0]))
        ->assertSessionHasNoErrors();

    expect($this->consumer->refresh()->is_default)->toBeTrue();
});

test('an admin updates a customer group, keeping its own code', function () {
    $group = CustomerGroup::factory()->business()->create();

    $this->put(route('admin.customer-groups.update', $group), groupPayload(['code' => 'b2b', 'name' => 'Trade', 'prices_include_tax' => 1]))
        ->assertSessionHasNoErrors();

    expect($group->refresh()->name)->toBe('Trade')->and($group->prices_include_tax)->toBeTrue();
});

test('only an unused non-default group can be deleted', function () {
    $unused = CustomerGroup::factory()->business()->create();
    $used = CustomerGroup::factory()->create();
    User::factory()->create(['customer_group_id' => $used->id]);

    $this->delete(route('admin.customer-groups.destroy', $this->consumer))->assertStatus(422);
    $this->delete(route('admin.customer-groups.destroy', $used))->assertStatus(422);
    $this->delete(route('admin.customer-groups.destroy', $unused))->assertRedirect();

    expect(CustomerGroup::query()->pluck('id')->all())->toEqualCanonicalizing([$this->consumer->id, $used->id]);
});
