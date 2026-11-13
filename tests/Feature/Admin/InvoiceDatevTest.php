<?php

use App\Datev\DatevExtfWriter;
use App\Enums\TaxTreatment;
use App\Invoicing\InvoiceIssuer;
use App\Models\CustomerGroup;
use App\Models\DatevExport;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['shop.datev.debtor_account' => '10000', 'shop.datev.festschreibung' => true, 'shop.datev.accounts' => [
        'domestic_19' => '4400', 'domestic_7' => '4300', 'domestic_0' => '4200', 'intra_community' => '4125', 'export' => '4120',
        'oss' => '4320', 'reverse_charge' => '4336', 'small_business' => '4185',
    ]]);
    CustomerGroup::factory()->consumer()->create();
    Storage::fake('local');
    $this->travelTo('2026-06-15 12:00:00');
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * @param  list<array{rate: float, category: string, net: int, tax: int, gross: int}>|null  $taxSummary
 */
function issuedInvoice(array $order = [], ?array $taxSummary = null): Invoice
{
    $order = Order::factory()->paid()->create([
        'number' => 'B-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'billing_address' => ['first_name' => 'Erika', 'last_name' => 'Mustermann', 'company' => null, 'address1' => 'Weg 1', 'address2' => null, 'zip' => '10115', 'city' => 'Berlin', 'country' => 'DE'],
        ...$order,
        ...($taxSummary === null ? [] : ['tax_summary' => $taxSummary]),
    ]);
    $order->items()->create([
        'name' => 'Testbuch', 'sku' => 'BK-1', 'quantity' => 1, 'unit_price_cents' => 1070, 'tax_class' => 'reduced',
        'tax_rate' => 7, 'tax_category' => 'S', 'discount_cents' => 0, 'line_net_cents' => 1000, 'line_tax_cents' => 70, 'line_gross_cents' => 1070,
    ]);

    return app(InvoiceIssuer::class)->issueInvoice($order);
}

/**
 * @return list<array<string, string|null>> bookings keyed by DATEV column
 */
function exportDatev(object $test, string $from = '2026-06-01', string $to = '2026-06-30'): array
{
    $test->post(route('admin.datev.store'), ['from' => $from, 'to' => $to])->assertSessionHasNoErrors();

    $csv = mb_convert_encoding(Storage::disk('local')->get(DatevExport::query()->latest('id')->firstOrFail()->path), 'UTF-8', 'Windows-1252');
    $lines = array_values(array_filter(explode("\r\n", $csv), fn (string $line) => $line !== ''));

    return array_map(fn (string $line) => array_combine(DatevExtfWriter::COLUMNS, str_getcsv($line, ';', '"')), array_slice($lines, 2));
}

// Invoice screens

test('an admin downloads the invoice PDF', function () {
    $invoice = issuedInvoice();

    $response = $this->get(route('admin.invoices.download', $invoice))->assertOk()->assertDownload($invoice->number.'.pdf');

    expect($response->streamedContent())->toStartWith('%PDF');
});

test('an invoice without a stored PDF is a 404', function () {
    $order = Order::factory()->paid()->create();
    $invoice = Invoice::query()->create([
        'type' => 'invoice', 'number' => 'RE-00099', 'order_id' => $order->id, 'issued_at' => now(), 'currency' => 'EUR',
        'total_net_cents' => 1, 'total_tax_cents' => 0, 'total_gross_cents' => 1, 'snapshot' => [],
    ]);

    $this->get(route('admin.invoices.download', $invoice))->assertNotFound();
});

test('the invoice list shows invoices and credit notes and can be searched by number', function () {
    $invoice = issuedInvoice();
    issuedInvoice();
    app(InvoiceIssuer::class)->issueCreditNote($invoice->order);

    $this->get(route('admin.invoices.index'))
        ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 3));

    $this->get(route('admin.invoices.index', ['q' => 'GS-']))
        ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1)->where('invoices.data.0.type', 'credit_note')->where('invoices.data.0.exported', false));
});

// DATEV export

test('the export file has an EXTF header, the column row and one booking per invoice', function () {
    issuedInvoice(['number' => 'B-00042']);

    $this->post(route('admin.datev.store'), ['from' => '2026-06-01', 'to' => '2026-06-30'])->assertRedirect(route('admin.datev.index'));

    $export = DatevExport::query()->sole();
    $csv = mb_convert_encoding(Storage::disk('local')->get($export->path), 'UTF-8', 'Windows-1252');
    $lines = explode("\r\n", $csv);

    expect($lines[0])->toStartWith('"EXTF";700;21;"Buchungsstapel";13;')
        ->and($lines[1])->toStartWith('Umsatz (ohne Soll/Haben-Kz);Soll/Haben-Kennzeichen;WKZ Umsatz;')
        ->and(count($lines))->toBe(4)
        ->and($lines[3])->toBe('')
        ->and($export->invoice_count)->toBe(1)
        ->and($export->booking_count)->toBe(1)
        ->and($export->sha256)->toBe(hash('sha256', Storage::disk('local')->get($export->path)))
        ->and($export->from_date->toDateString())->toBe('2026-06-01');
});

test('a domestic invoice is booked gross against the debtor and the revenue account of its rate', function (float $rate, string $account) {
    issuedInvoice(taxSummary: [['rate' => $rate, 'category' => 'S', 'net' => 1000, 'tax' => 0, 'gross' => 1070]]);

    $booking = exportDatev($this)[0];

    expect($booking['Umsatz (ohne Soll/Haben-Kz)'])->toBe('10,70')
        ->and($booking['Soll/Haben-Kennzeichen'])->toBe('S')
        ->and($booking['WKZ Umsatz'])->toBe('EUR')
        ->and($booking['Konto'])->toBe('10000')
        ->and($booking['Gegenkonto (ohne BU-Schlüssel)'])->toBe($account)
        ->and($booking['Belegdatum'])->toBe('1506')
        ->and($booking['Belegfeld 1'])->toBe('RE-00001')
        ->and($booking['Buchungstext'])->toStartWith('Rechnung B-')->toContain('Erika Mustermann')
        ->and($booking['Festschreibung'])->toBe('1')
        ->and($booking['EU-Land u. UStID (Bestimmung)'])->toBe('');
})->with([
    '19 %' => [19.0, '4400'],
    '7 %' => [7.0, '4300'],
    '0 %' => [0.0, '4200'],
]);

test('a credit note is booked on the credit side with its own document number', function () {
    $invoice = issuedInvoice(['number' => 'B-00007']);
    app(InvoiceIssuer::class)->issueCreditNote($invoice->order);

    $bookings = exportDatev($this);

    expect($bookings)->toHaveCount(2)
        ->and($bookings[0])->toMatchArray(['Soll/Haben-Kennzeichen' => 'S', 'Belegfeld 1' => 'RE-00001'])
        ->and($bookings[1])->toMatchArray([
            'Umsatz (ohne Soll/Haben-Kz)' => '10,70',
            'Soll/Haben-Kennzeichen' => 'H',
            'Belegfeld 1' => 'GS-00001',
            'Belegfeld 2' => '00007',
            'Gegenkonto (ohne BU-Schlüssel)' => '4300',
        ])
        ->and($bookings[1]['Buchungstext'])->toStartWith('Gutschrift B-00007');
});

test('an intra-community invoice goes to the tax-free account with the buyer VAT ID', function () {
    issuedInvoice(Order::factory()->intraCommunity()->raw(['number' => 'B-00001']), [['rate' => 0.0, 'category' => 'K', 'net' => 1000, 'tax' => 0, 'gross' => 1000]]);

    $booking = exportDatev($this)[0];

    expect($booking)->toMatchArray([
        'Umsatz (ohne Soll/Haben-Kz)' => '10,00',
        'Gegenkonto (ohne BU-Schlüssel)' => '4125',
        'EU-Land u. UStID (Bestimmung)' => 'ATU12345678',
        'EU-Steuersatz (Bestimmung)' => '',
    ])->and($booking['Buchungstext'])->toContain('Buchhandlung GmbH');
});

test('an OSS invoice goes to the OSS account with destination country and rate', function () {
    issuedInvoice([
        'country' => 'AT', 'tax_treatment' => TaxTreatment::Oss,
        'billing_address' => ['first_name' => 'Erika', 'last_name' => 'Mustermann', 'company' => null, 'address1' => 'Weg 1', 'address2' => null, 'zip' => '1010', 'city' => 'Wien', 'country' => 'AT'],
    ], [['rate' => 10.0, 'category' => 'S', 'net' => 1000, 'tax' => 100, 'gross' => 1100]]);

    $booking = exportDatev($this)[0];

    expect($booking)->toMatchArray([
        'Umsatz (ohne Soll/Haben-Kz)' => '11,00',
        'Gegenkonto (ohne BU-Schlüssel)' => '4320',
        'EU-Land u. UStID (Bestimmung)' => 'AT',
        'EU-Steuersatz (Bestimmung)' => '10,00',
    ]);
});

test('the revenue account follows the treatment and the configured account numbers', function (TaxTreatment $treatment, string $key) {
    config(["shop.datev.accounts.{$key}" => '4999']);
    issuedInvoice(['tax_treatment' => $treatment, 'country' => 'CH'], [['rate' => 0.0, 'category' => 'G', 'net' => 1000, 'tax' => 0, 'gross' => 1000]]);

    expect(exportDatev($this)[0]['Gegenkonto (ohne BU-Schlüssel)'])->toBe('4999');
})->with([
    [TaxTreatment::IntraCommunity, 'intra_community'],
    [TaxTreatment::Export, 'export'],
    [TaxTreatment::ReverseCharge, 'reverse_charge'],
    [TaxTreatment::SmallBusiness, 'small_business'],
    [TaxTreatment::Oss, 'oss'],
]);

test('each tax group of an invoice becomes its own booking', function () {
    issuedInvoice(taxSummary: [
        ['rate' => 19.0, 'category' => 'S', 'net' => 2000, 'tax' => 380, 'gross' => 2380],
        ['rate' => 7.0, 'category' => 'S', 'net' => 1000, 'tax' => 70, 'gross' => 1070],
    ]);

    $bookings = exportDatev($this);

    expect($bookings)->toHaveCount(2)
        ->and(array_column($bookings, 'Gegenkonto (ohne BU-Schlüssel)'))->toBe(['4400', '4300'])
        ->and(array_column($bookings, 'Umsatz (ohne Soll/Haben-Kz)'))->toBe(['23,80', '10,70'])
        ->and($bookings[0]['Buchungs GUID'])->not->toBe($bookings[1]['Buchungs GUID']);

    expect(DatevExport::query()->sole()->only(['invoice_count', 'booking_count']))->toBe(['invoice_count' => 1, 'booking_count' => 2]);
});

test('exported invoices are marked and never exported twice', function () {
    $invoice = issuedInvoice();

    $this->post(route('admin.datev.store'), ['from' => '2026-06-01', 'to' => '2026-06-30'])->assertSessionHasNoErrors();

    expect($invoice->refresh()->datev_export_id)->toBe(DatevExport::query()->sole()->id);

    $this->post(route('admin.datev.store'), ['from' => '2026-06-01', 'to' => '2026-06-30'])->assertSessionHasErrors('from');

    expect(DatevExport::query()->count())->toBe(1);
});

test('only invoices issued in the period are exported', function () {
    $this->travelTo('2026-05-10 12:00:00');
    $may = issuedInvoice();
    $this->travelTo('2026-06-15 12:00:00');
    $june = issuedInvoice();

    $this->post(route('admin.datev.store'), ['from' => '2026-06-01', 'to' => '2026-06-30'])->assertSessionHasNoErrors();

    expect($may->refresh()->datev_export_id)->toBeNull()
        ->and($june->refresh()->datev_export_id)->not->toBeNull()
        ->and(DatevExport::query()->sole()->invoice_count)->toBe(1);
});

test('an empty period is reported instead of producing an empty file', function () {
    $this->post(route('admin.datev.store'), ['from' => '2026-06-01', 'to' => '2026-06-30'])->assertSessionHasErrors('from');

    expect(DatevExport::query()->count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('datev');
});

test('a domestic rate without a mapped account aborts the whole export', function () {
    $invoice = issuedInvoice(taxSummary: [['rate' => 5.0, 'category' => 'S', 'net' => 1000, 'tax' => 50, 'gross' => 1050]]);

    $this->post(route('admin.datev.store'), ['from' => '2026-06-01', 'to' => '2026-06-30'])->assertSessionHasErrors('from');

    expect(DatevExport::query()->count())->toBe(0)->and($invoice->refresh()->datev_export_id)->toBeNull();
});

test('the export period is validated', function (array $payload, string $field) {
    $this->post(route('admin.datev.store'), $payload)->assertSessionHasErrors($field);
})->with([
    'from missing' => [['to' => '2026-06-30'], 'from'],
    'to missing' => [['from' => '2026-06-01'], 'to'],
    'not a date' => [['from' => 'soon', 'to' => '2026-06-30'], 'from'],
    'to before from' => [['from' => '2026-06-30', 'to' => '2026-06-01'], 'to'],
]);

test('an admin downloads a stored export as Windows-1252 CSV', function () {
    issuedInvoice();
    $this->post(route('admin.datev.store'), ['from' => '2026-06-01', 'to' => '2026-06-30']);
    $export = DatevExport::query()->sole();

    $response = $this->get(route('admin.datev.download', $export))->assertOk()->assertDownload(basename($export->path));

    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=Windows-1252')
        ->and(hash('sha256', $response->streamedContent()))->toBe($export->sha256);
});
