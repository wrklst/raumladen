<?php

use App\Mail\NewOrder;
use App\Mail\OrderConfirmation;
use App\Models\Author;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Orders\MarkOrderPaid;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
 * A book's authors are entries of their own, linked to it; their names are
 * kept on the book as text for listings and search.
 */
test('authors are linked to a book in order, new names become entries, and the text follows', function () {
    $this->actingAs(User::factory()->admin()->create());
    $product = Product::factory()->create(['specs' => ['isbn' => '978-3-00-000001-1']]);
    [$flores, $palazzi] = [Author::factory()->create(['name' => 'Thomas Flores']), Author::factory()->create(['name' => 'Mathias Palazzi'])];

    $this->put(route('admin.products.update', $product), productPayload(['specs' => ['isbn' => '978-3-00-000001-1'], 'author_ids' => [$palazzi->id, $flores->id], 'new_authors' => 'Zara Pfeifer, thomas flores']))
        ->assertSessionHasNoErrors();

    // In the order chosen, the new one last; a name the shop knows is not entered twice.
    expect($product->refresh()->authors->pluck('name')->all())->toBe(['Mathias Palazzi', 'Thomas Flores', 'Zara Pfeifer'])
        ->and($product->specs['authors'])->toBe('Mathias Palazzi, Thomas Flores, Zara Pfeifer')
        ->and($product->specs['isbn'])->toBe('978-3-00-000001-1')
        ->and(Author::query()->count())->toBe(3);

    // A rename and a removal reach the book.
    $this->put(route('admin.authors.update', $flores), ['name' => 'Tom Flores']);
    $this->delete(route('admin.authors.destroy', $palazzi));
    expect($product->refresh()->specs['authors'])->toBe('Tom Flores, Zara Pfeifer');

    // No author at all leaves no text behind.
    $this->put(route('admin.products.update', $product), productPayload());
    expect($product->refresh()->authors)->toHaveCount(0)->and($product->specs)->not->toHaveKey('authors');

    $this->put(route('admin.products.update', $product), productPayload(['author_ids' => [99999]]))->assertSessionHasErrors('author_ids.0');
});

test('the people named in the settings are told once when an order is placed', function () {
    config(['shop.orders.notify' => ['versand@example.org', 'vorstand@example.org'], 'shop.seller.iban' => 'DE02120300000000202051']);
    config(['shop.fixed_price_countries' => [], 'shop.tax.small_business' => false]);
    CustomerGroup::factory()->consumer()->create();
    Storage::fake('local');
    Mail::fake();
    $product = Product::factory()->create(['stock' => 5]);

    // Prepayment: placed at checkout, so told at once; the later payment does not tell them again.
    $this->withSession(['cart.items' => [$product->id => 1]])->post(route('shop.checkout.store', ['locale' => 'de']), [
        'email' => 'kunde@example.com',
        'billing_address' => ['first_name' => 'Erika', 'last_name' => 'Mustermann', 'address1' => 'Weg 1', 'zip' => '10115', 'city' => 'Berlin', 'country' => 'DE'],
        'gateway' => 'bank_transfer',
    ]);

    Mail::assertSent(NewOrder::class, 2);
    Mail::assertSent(NewOrder::class, fn (NewOrder $mail) => $mail->hasTo('versand@example.org'));

    app(MarkOrderPaid::class)->handle(Order::query()->sole(), 'bank_transfer');
    Mail::assertSent(NewOrder::class, 2);
    Mail::assertSent(OrderConfirmation::class, 1);

    // Paid online: placed by the payment, and that is when they are told.
    $draft = Order::factory()->create(['placed_at' => null]);
    app(MarkOrderPaid::class)->handle($draft, 'mollie');
    Mail::assertSent(NewOrder::class, 4);
});

test('the addresses to tell must be email addresses', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.update', 'mail'), ['mail_mailer' => 'log', 'mail_from_address' => 'shop@example.org', 'mail_from_name' => 'Shop', 'order_notify' => "ok@example.org\nnot-an-address"])
        ->assertSessionHasErrors('order_notify');
});
