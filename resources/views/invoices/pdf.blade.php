@php
    use App\Support\Money;
    $isCredit = $s['type'] === 'credit_note';
    $cur = $s['currency'];
    $loc = $s['locale'];
    $m = fn (int $cents) => Money::format($cents, $cur, $loc);
    $seller = $s['seller'];
    $buyer = $s['buyer'];
    $gross = $s['prices_include_tax'];
@endphp
<!DOCTYPE html>
<html lang="{{ $loc }}">
<head>
    <meta charset="utf-8">
    <title>{{ $s['number'] }}</title>
    <style>
        @page { margin: 20mm 18mm 25mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #111; line-height: 1.4; }
        .sender { font-size: 7pt; color: #666; border-bottom: 0.5pt solid #999; padding-bottom: 2px; margin-bottom: 6px; width: 85mm; }
        .addr { width: 85mm; min-height: 28mm; }
        .meta { position: absolute; right: 0; top: 0; width: 70mm; font-size: 9pt; }
        .meta td { padding: 1px 4px; }
        .meta td:first-child { color: #666; }
        h1 { font-size: 15pt; margin: 14mm 0 4mm; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 4mm; }
        table.lines th { text-align: left; border-bottom: 1pt solid #111; padding: 4px 3px; font-size: 8.5pt; }
        table.lines td { padding: 4px 3px; border-bottom: 0.3pt solid #ccc; vertical-align: top; }
        .r { text-align: right; }
        table.totals { margin-left: auto; margin-top: 4mm; border-collapse: collapse; width: 75mm; }
        table.totals td { padding: 2px 4px; }
        table.totals tr.total td { border-top: 1pt solid #111; font-weight: bold; padding-top: 4px; }
        .note { margin-top: 6mm; font-size: 9pt; }
        .footer { position: fixed; bottom: -15mm; left: 0; right: 0; font-size: 7pt; color: #666; border-top: 0.5pt solid #999; padding-top: 3px; }
        .footer td { vertical-align: top; width: 33%; padding-right: 8px; }
    </style>
</head>
<body>
    <div style="position: relative;">
        <div class="sender">{{ $seller['name'] }} · {{ $seller['address1'] }} · {{ $seller['zip'] }} {{ $seller['city'] }}</div>
        <div class="addr">
            @if($buyer['company']){{ $buyer['company'] }}<br>@endif
            {{ $buyer['name'] }}<br>
            {{ $buyer['address1'] }}<br>
            @if($buyer['address2']){{ $buyer['address2'] }}<br>@endif
            {{ $buyer['zip'] }} {{ $buyer['city'] }}<br>
            @if($buyer['country'] !== $seller['country']){{ $buyer['country_name'] }}@endif
        </div>
        <table class="meta">
            <tr><td>{{ $isCredit ? __('Credit note no.') : __('Invoice no.') }}</td><td><strong>{{ $s['number'] }}</strong></td></tr>
            <tr><td>{{ __('Date') }}</td><td>{{ \Carbon\Carbon::parse($s['issued_at'])->translatedFormat('d.m.Y') }}</td></tr>
            <tr><td>{{ __('Service date') }}</td><td>{{ \Carbon\Carbon::parse($s['service_date'])->translatedFormat('d.m.Y') }}</td></tr>
            <tr><td>{{ __('Order no.') }}</td><td>{{ $s['order']['number'] }}</td></tr>
            @if($s['references'])
                <tr><td>{{ __('Refers to invoice') }}</td><td>{{ $s['references']['number'] }}</td></tr>
            @endif
            @if($buyer['vat_id'])
                <tr><td>{{ __('Your VAT ID') }}</td><td>{{ $buyer['vat_id'] }}</td></tr>
            @endif
            @if($seller['vat_id'])
                <tr><td>{{ __('Our VAT ID') }}</td><td>{{ $seller['vat_id'] }}</td></tr>
            @elseif($seller['tax_number'])
                <tr><td>{{ __('Tax number') }}</td><td>{{ $seller['tax_number'] }}</td></tr>
            @endif
        </table>
    </div>

    <h1>{{ $isCredit ? __('Credit note') : __('Invoice') }}</h1>

    <table class="lines">
        <thead>
            <tr>
                <th style="width: 6%">{{ __('Pos.') }}</th>
                <th>{{ __('Description') }}</th>
                <th class="r" style="width: 8%">{{ __('Qty') }}</th>
                <th class="r" style="width: 15%">{{ $gross ? __('Unit price (gross)') : __('Unit price (net)') }}</th>
                <th class="r" style="width: 8%">{{ __('VAT') }}</th>
                <th class="r" style="width: 15%">{{ $gross ? __('Total (gross)') : __('Total (net)') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($s['lines'] as $line)
                <tr>
                    <td>{{ $line['position'] }}</td>
                    <td>{{ $line['name'] }}@if($line['sku'])<br><span style="color:#666;font-size:8pt">{{ $line['sku'] }}</span>@endif</td>
                    <td class="r">{{ $line['quantity'] }}</td>
                    <td class="r">{{ $m($line['unit_price_cents']) }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format($line['tax_rate'], 2, '.', ''), '0'), '.') }} %</td>
                    <td class="r">{{ $m($gross ? $line['gross'] + $line['discount_cents'] : $line['net'] + $line['discount_cents']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @if($s['coupon'])
            <tr><td>{{ __('Discount') }} ({{ $s['coupon']['code'] }})</td><td class="r">− {{ $m($s['coupon']['discount_cents']) }}</td></tr>
        @endif
        @if($s['shipping'])
            <tr><td>{{ __('Shipping') }}</td><td class="r">{{ $m($gross ? $s['shipping']['gross'] : $s['shipping']['net']) }}</td></tr>
        @endif
        <tr><td>{{ __('Net total') }}</td><td class="r">{{ $m($s['totals']['net']) }}</td></tr>
        @foreach($s['tax_groups'] as $group)
            @if($group['tax'] > 0 || $group['rate'] > 0)
                <tr><td>{{ __('VAT') }} {{ rtrim(rtrim(number_format($group['rate'], 2, '.', ''), '0'), '.') }} % {{ __('on') }} {{ $m($group['net']) }}</td><td class="r">{{ $m($group['tax']) }}</td></tr>
            @endif
        @endforeach
        <tr class="total"><td>{{ $isCredit ? __('Credit amount') : __('Total') }}</td><td class="r">{{ $m($s['totals']['gross']) }}</td></tr>
    </table>

    <div class="note">
        @if($s['tax_note'])
            <p><strong>{{ $s['tax_note'] }}</strong></p>
        @endif
        @if($isCredit)
            <p>{{ __('This credit note cancels the referenced invoice. The amount will be refunded via the original payment method.') }}</p>
        @elseif($s['payment']['paid_at'])
            <p>{{ __('Paid on :date via :method. Thank you for your order.', ['date' => \Carbon\Carbon::parse($s['payment']['paid_at'])->translatedFormat('d.m.Y'), 'method' => ucfirst((string) $s['payment']['gateway'])]) }}</p>
        @else
            <p>{{ __('Payable until :date.', ['date' => \Carbon\Carbon::parse($s['due_date'])->translatedFormat('d.m.Y')]) }}</p>
        @endif
    </div>

    <table class="footer">
        <tr>
            <td>{{ $seller['name'] }}<br>{{ $seller['address1'] }}<br>{{ $seller['zip'] }} {{ $seller['city'] }}<br>{{ $seller['email'] }}</td>
            <td>{{ $seller['register'] }}<br>@if($seller['representatives']){{ __('Represented by') }}: {{ $seller['representatives'] }}<br>@endif @if($seller['vat_id']){{ __('VAT ID') }}: {{ $seller['vat_id'] }}@endif</td>
            <td>@if($seller['iban']){{ $seller['bank'] }}<br>IBAN {{ $seller['iban'] }}<br>BIC {{ $seller['bic'] }}@endif</td>
        </tr>
    </table>
</body>
</html>
