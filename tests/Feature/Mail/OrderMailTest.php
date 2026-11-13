<?php

use App\Mail\OrderConfirmation;
use App\Mail\OrderShipped;
use App\Models\Invoice;
use App\Models\Order;
use App\Orders\MarkOrderPaid;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    Storage::fake('local');
});

function mailableOrder(string $locale, string $country = 'DE'): Order
{
    $address = ['first_name' => 'Erika', 'last_name' => 'Mustermann', 'company' => null, 'address1' => 'Weg 1', 'address2' => null, 'zip' => '10115', 'city' => 'Berlin', 'country' => $country];
    $order = Order::factory()->create([
        'number' => 'B-00042', 'locale' => $locale, 'email' => 'kunde@example.com',
        'billing_address' => $address, 'shipping_address' => $address, 'country' => $country,
    ]);
    $order->items()->create([
        'name' => 'Testbuch', 'sku' => 'BK-1', 'quantity' => 2, 'unit_price_cents' => 535, 'tax_class' => 'reduced',
        'tax_rate' => 7, 'tax_category' => 'S', 'discount_cents' => 0, 'line_net_cents' => 1000, 'line_tax_cents' => 70, 'line_gross_cents' => 1070,
    ]);

    return $order;
}

/**
 * The message the array mailer actually "sent".
 */
function sentEmail(): Email
{
    $messages = Mail::mailer('array')->getSymfonyTransport()->messages();

    return $messages->last()->getOriginalMessage();
}

test('the order confirmation has no attachment while the invoice has no PDF', function () {
    $order = mailableOrder('de');
    $invoice = new Invoice(['number' => 'RE-00001']);

    expect((new OrderConfirmation($order, $invoice))->attachments())->toBe([]);
});

test('the sent confirmation carries the PDF bytes and the order in its locale', function (string $locale, string $subjectStart, string $country, string $total) {
    $order = mailableOrder($locale, 'AT');

    app(MarkOrderPaid::class)->handle($order, 'mollie');

    $email = sentEmail();
    $attachment = $email->getAttachments()[0];

    expect($email->getTo()[0]->getAddress())->toBe('kunde@example.com')
        ->and($email->getSubject())->toBe($subjectStart.' B-00042')
        ->and($email->getHtmlBody())->toContain('B-00042')->toContain('Testbuch')->toContain($country)->toContain($total)
        ->and($attachment->getFilename())->toBe('RE-00001.pdf')
        ->and($attachment->getMediaType().'/'.$attachment->getMediaSubtype())->toBe('application/pdf')
        ->and($attachment->getBody())->toStartWith('%PDF');
})->with([
    'german' => ['de', 'Bestellbestätigung', 'Österreich', '10,70'],
    'english' => ['en', 'Order confirmation', 'Austria', '€10.70'],
]);

test('the confirmation links to the order in the order locale', function (string $locale) {
    $order = mailableOrder($locale);

    app(MarkOrderPaid::class)->handle($order, 'mollie');

    expect(sentEmail()->getHtmlBody())->toContain(route('shop.account.orders.show', ['locale' => $locale, 'order' => $order]));
})->with(['de', 'en']);

test('the shipping mail renders the address and a link in the order locale', function (string $locale, string $subjectStart, string $country) {
    $order = mailableOrder($locale, 'AT');
    $mailable = new OrderShipped($order);

    $mailable->assertSeeInHtml('B-00042');
    $mailable->assertSeeInHtml('Erika Mustermann');
    $mailable->assertSeeInHtml('Weg 1');
    $mailable->assertSeeInHtml(route('shop.account.orders.show', ['locale' => $locale, 'order' => $order]), false);

    Mail::to($order->email)->locale($locale)->send($mailable);

    $email = sentEmail();

    expect($email->getSubject())->toBe($subjectStart.' B-00042'.($locale === 'de' ? ' wurde versandt' : ' has been shipped'))
        ->and($email->getHtmlBody())->toContain($country);
})->with([
    'german' => ['de', 'Ihre Bestellung', 'Österreich'],
    'english' => ['en', 'Your order', 'Austria'],
]);
