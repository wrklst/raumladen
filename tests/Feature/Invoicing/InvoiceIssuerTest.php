<?php

use App\Enums\InvoiceType;
use App\Enums\TaxTreatment;
use App\Invoicing\InvoiceIssuer;
use App\Invoicing\InvoicePdf;
use App\Models\Invoice;
use App\Models\NumberSequence;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-06-15 12:00:00');
});

function invoiceableOrder(array $attributes = []): Order
{
    $order = Order::factory()->paid()->create([
        'paid_at' => now()->subDay(),
        'tax_note' => null,
        ...$attributes,
    ]);
    $order->items()->create([
        'name' => 'Testbuch', 'sku' => 'BK-1', 'quantity' => 1, 'unit_price_cents' => 1070, 'tax_class' => 'reduced',
        'tax_rate' => 7, 'tax_category' => 'S', 'discount_cents' => 0, 'line_net_cents' => 1000, 'line_tax_cents' => 70, 'line_gross_cents' => 1070,
    ]);

    return $order;
}

test('the invoice snapshot captures seller, buyer, lines, tax groups and totals', function () {
    config(['shop.seller.vat_id' => 'DE123456789']);
    $order = invoiceableOrder([
        'locale' => 'de',
        'email' => 'kunde@example.com',
        'vat_id' => 'ATU12345678',
        'vat_id_valid' => true,
        'shipping_net_cents' => 364,
        'shipping_tax_cents' => 26,
        'shipping_tax_rate' => 7,
        'subtotal_net_cents' => 1000,
        'tax_cents' => 96,
        'total_cents' => 1460,
        'coupon_code' => 'TEN',
        'discount_cents' => 100,
        'billing_address' => ['first_name' => 'Erika', 'last_name' => 'Mustermann', 'company' => 'Buch GmbH', 'address1' => 'Weg 1', 'address2' => null, 'zip' => '10115', 'city' => 'Berlin', 'country' => 'DE'],
    ]);

    $invoice = app(InvoiceIssuer::class)->issueInvoice($order);
    $s = $invoice->refresh()->snapshot;

    expect($invoice->type)->toBe(InvoiceType::Invoice)
        ->and($invoice->number)->toBe('RE-00001')
        ->and($invoice->order_id)->toBe($order->id)
        ->and($invoice->issued_at->toDateString())->toBe('2026-06-15')
        ->and($invoice->references_invoice_id)->toBeNull()
        ->and($invoice->only(['total_net_cents', 'total_tax_cents', 'total_gross_cents']))->toBe(['total_net_cents' => 1364, 'total_tax_cents' => 96, 'total_gross_cents' => 1460])
        ->and($s['number'])->toBe('RE-00001')
        ->and($s['issued_at'])->toBe('2026-06-15')
        ->and($s['service_date'])->toBe('2026-06-14')
        ->and($s['due_date'])->toBe('2026-06-29')
        ->and($s['references'])->toBeNull()
        ->and($s['order'])->toMatchArray(['number' => $order->number, 'uuid' => $order->uuid])
        ->and($s['seller']['name'])->toBe(config('shop.seller.name'))
        ->and($s['seller']['vat_id'])->toBe('DE123456789')
        ->and($s['buyer'])->toMatchArray([
            'name' => 'Erika Mustermann', 'company' => 'Buch GmbH', 'address1' => 'Weg 1', 'zip' => '10115', 'city' => 'Berlin',
            'country' => 'DE', 'country_name' => 'Deutschland', 'email' => 'kunde@example.com', 'vat_id' => 'ATU12345678',
        ])
        ->and($s['tax_treatment'])->toBe('domestic')
        ->and($s['tax_category'])->toBe('S')
        ->and($s['prices_include_tax'])->toBeTrue()
        ->and($s['lines'])->toEqual([[
            'position' => 1, 'name' => 'Testbuch', 'sku' => 'BK-1', 'quantity' => 1, 'unit_price_cents' => 1070, 'discount_cents' => 0,
            'tax_rate' => 7.0, 'tax_category' => 'S', 'net' => 1000, 'tax' => 70, 'gross' => 1070,
        ]])
        ->and($s['shipping'])->toEqual(['net' => 364, 'tax' => 26, 'gross' => 390, 'tax_rate' => 7.0])
        ->and($s['coupon'])->toBe(['code' => 'TEN', 'discount_cents' => 100])
        ->and($s['tax_groups'])->toEqual($order->tax_summary)
        ->and($s['totals'])->toBe(['net' => 1364, 'tax' => 96, 'gross' => 1460])
        ->and($s['payment'])->toBe(['gateway' => 'mollie', 'paid_at' => $order->paid_at->toDateTimeString()]);
});

test('the buyer VAT ID is printed only when it was confirmed', function () {
    $order = invoiceableOrder(['vat_id' => 'ATU12345678', 'vat_id_valid' => false]);

    $snapshot = app(InvoiceIssuer::class)->issueInvoice($order)->snapshot;

    expect($snapshot['buyer']['vat_id'])->toBeNull()
        ->and($snapshot['shipping'])->toBeNull()
        ->and($snapshot['coupon'])->toBeNull();
});

test('an intra-community invoice carries the legal note and the exempt category', function () {
    $order = invoiceableOrder(['tax_treatment' => TaxTreatment::IntraCommunity, 'tax_note' => TaxTreatment::IntraCommunity->note('de')]);

    $snapshot = app(InvoiceIssuer::class)->issueInvoice($order)->snapshot;

    expect($snapshot['tax_treatment'])->toBe('intra_community')
        ->and($snapshot['tax_category'])->toBe('K')
        ->and($snapshot['tax_note'])->toContain('§ 4 Nr. 1b');
});

test('invoice numbers are sequential and gapless', function () {
    $issuer = app(InvoiceIssuer::class);

    $numbers = collect(range(1, 3))->map(fn () => $issuer->issueInvoice(invoiceableOrder())->number)->all();

    expect($numbers)->toBe(['RE-00001', 'RE-00002', 'RE-00003'])
        ->and(NumberSequence::query()->find('invoice')->next_number)->toBe(4);
});

test('issuing twice for the same order returns the same invoice', function () {
    $order = invoiceableOrder();
    $issuer = app(InvoiceIssuer::class);

    $first = $issuer->issueInvoice($order);
    $second = $issuer->issueInvoice($order);

    expect($second->is($first))->toBeTrue()
        ->and(Invoice::query()->count())->toBe(1)
        ->and(NumberSequence::query()->find('invoice')->next_number)->toBe(2);
});

test('a failed issue releases the claimed number again', function () {
    $order = invoiceableOrder();
    config(['shop.invoice.disk' => 'missing-disk']);

    expect(fn () => app(InvoiceIssuer::class)->issueInvoice($order))->toThrow(InvalidArgumentException::class);
    expect(Invoice::query()->count())->toBe(0);

    config(['shop.invoice.disk' => 'local']);

    expect(app(InvoiceIssuer::class)->issueInvoice($order)->number)->toBe('RE-00001');
});

test('the PDF is stored under the year and number', function () {
    $invoice = app(InvoiceIssuer::class)->issueInvoice(invoiceableOrder());

    expect($invoice->pdf_path)->toBe('invoices/2026/RE-00001.pdf')
        ->and(Storage::disk('local')->get($invoice->pdf_path))->toStartWith('%PDF');
});

test('the PDF renderer returns a PDF in the invoice locale and restores the app locale', function (string $locale) {
    app()->setLocale('de');
    $invoice = app(InvoiceIssuer::class)->issueInvoice(invoiceableOrder(['locale' => $locale]));

    $pdf = app(InvoicePdf::class)->render($invoice);

    expect($pdf)->toStartWith('%PDF')->and(app()->getLocale())->toBe('de');
})->with(['de', 'en']);

test('a credit note references the invoice and uses its own sequence', function () {
    $issuer = app(InvoiceIssuer::class);
    $order = invoiceableOrder();
    $invoice = $issuer->issueInvoice($order);

    $credit = $issuer->issueCreditNote($order);

    expect($credit->type)->toBe(InvoiceType::CreditNote)
        ->and($credit->isCreditNote())->toBeTrue()
        ->and($credit->number)->toBe('GS-00001')
        ->and($credit->references_invoice_id)->toBe($invoice->id)
        ->and($credit->references->is($invoice))->toBeTrue()
        ->and($credit->snapshot['type'])->toBe('credit_note')
        ->and($credit->snapshot['references'])->toBe(['number' => 'RE-00001', 'issued_at' => '2026-06-15'])
        ->and($credit->total_gross_cents)->toBe($invoice->total_gross_cents)
        ->and($credit->pdf_path)->toBe('invoices/2026/GS-00001.pdf')
        ->and(NumberSequence::query()->find('invoice')->next_number)->toBe(2)
        ->and(NumberSequence::query()->find('credit_note')->next_number)->toBe(2);
    Storage::disk('local')->assertExists($credit->pdf_path);
});

test('issuing a credit note twice returns the same one', function () {
    $issuer = app(InvoiceIssuer::class);
    $order = invoiceableOrder();
    $issuer->issueInvoice($order);

    expect($issuer->issueCreditNote($order)->is($issuer->issueCreditNote($order)))->toBeTrue()
        ->and(Invoice::query()->count())->toBe(2);
});

test('an order without an invoice cannot be credited', function () {
    app(InvoiceIssuer::class)->issueCreditNote(invoiceableOrder());
})->throws(LogicException::class, 'Cannot credit an order without an invoice.');

test('issued documents are immutable and cannot be deleted', function () {
    $invoice = app(InvoiceIssuer::class)->issueInvoice(invoiceableOrder());

    expect(fn () => $invoice->update(['total_gross_cents' => 1]))->toThrow(LogicException::class, 'immutable')
        ->and(fn () => $invoice->delete())->toThrow(LogicException::class, 'cannot be deleted');
});

test('number sequences count per key and format with the configured prefix', function () {
    expect(NumberSequence::claim('a'))->toBe(1)
        ->and(NumberSequence::claim('a'))->toBe(2)
        ->and(NumberSequence::claim('b'))->toBe(1)
        ->and(NumberSequence::nextFormatted('order'))->toBe('B-00001')
        ->and(NumberSequence::nextFormatted('order'))->toBe('B-00002')
        ->and(NumberSequence::nextFormatted('credit_note'))->toBe('GS-00001');
});

test('a number beyond the configured digits keeps all of its digits', function () {
    NumberSequence::query()->create(['key' => 'order', 'next_number' => 99999]);

    expect(NumberSequence::nextFormatted('order'))->toBe('B-99999')
        ->and(NumberSequence::nextFormatted('order'))->toBe('B-100000');
});
