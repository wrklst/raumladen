<x-mail::message>
# {{ __('Thank you for your order') }}

{{ __('We have received your order :number of :date.', ['number' => $order->number, 'date' => $order->created_at->timezone('Europe/Berlin')->format('d.m.Y')]) }}

<x-mail::table>
| {{ __('Item') }} | {{ __('Qty') }} | {{ __('Total') }} |
|:-----------------|:---------------:|------------------:|
@foreach($order->items as $item)
| {{ $item->name }} | {{ $item->quantity }} | {{ \App\Support\Money::format($order->prices_include_tax ? $item->line_gross_cents : $item->line_net_cents, $order->currency) }} |
@endforeach
| {{ __('Shipping') }} | | {{ \App\Support\Money::format($order->prices_include_tax ? $order->shipping_net_cents + $order->shipping_tax_cents : $order->shipping_net_cents, $order->currency) }} |
| {{ __('VAT') }} | | {{ \App\Support\Money::format($order->tax_cents, $order->currency) }} |
| **{{ __('Total') }}** | | **{{ \App\Support\Money::format($order->total_cents, $order->currency) }}** |
</x-mail::table>

@if($bank)
## {{ __('Please transfer the amount') }}

{{ __('Amount') }}: {{ \App\Support\Money::format($order->total_cents, $order->currency) }}
{{ __('Recipient') }}: {{ $bank['name'] }}
IBAN: {{ $bank['iban'] }}
BIC: {{ $bank['bic'] }}
{{ __('Reference') }}: {{ $order->number }}

{{ __('We ship as soon as the payment arrives; your invoice follows by email.') }}
@endif

{{ __('Shipping to') }}:
{{ $order->shipping_address['first_name'] }} {{ $order->shipping_address['last_name'] }}, {{ $order->shipping_address['address1'] }}, {{ $order->shipping_address['zip'] }} {{ $order->shipping_address['city'] }}, {{ \App\Support\Countries::name($order->shipping_address['country'], $order->locale) }}

<x-mail::button :url="route('shop.account.orders.show', ['locale' => $order->locale, 'order' => $order])">
{{ __('View order') }}
</x-mail::button>

{{ config('shop.seller.name') }}

@foreach($legal as $page)
---

# {{ $page->translate('title', $order->locale) }}

{!! $page->renderedBody($order->locale) !!}
@endforeach
</x-mail::message>
