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
    {{-- Colours, type and rules of the invoice: a theme may replace this partial alone. --}}
    @include('invoices.style')
</head>
<body>
    @if($logo = \App\Settings\Appearance::logoDataUri())
        <div style="text-align: right; margin-bottom: 6mm;"><img src="{{ $logo }}" alt="{{ $seller['name'] }}" style="max-height: 18mm; max-width: 60mm;"></div>
    @endif
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

    @if(config('shop.appearance.invoice_note'))
        <div class="note"><p>{!! nl2br(e(config('shop.appearance.invoice_note'))) !!}</p></div>
    @endif

    <table class="footer">
        <tr>
            <td>{{ $seller['name'] }}<br>{{ $seller['address1'] }}<br>{{ $seller['zip'] }} {{ $seller['city'] }}<br>{{ $seller['email'] }}</td>
            <td>{{ $seller['register'] }}<br>@if($seller['representatives']){{ __('Represented by') }}: {{ $seller['representatives'] }}<br>@endif @if($seller['vat_id']){{ __('VAT ID') }}: {{ $seller['vat_id'] }}@endif</td>
            <td>@if($seller['iban']){{ $seller['bank'] }}<br>IBAN {{ $seller['iban'] }}<br>BIC {{ $seller['bic'] }}@endif</td>
        </tr>
    </table>
</body>
</html>
