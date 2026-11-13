<x-mail::message>
# {{ __('Thank you for your order') }}

{{ __('We have received your payment for order :number. Your invoice is attached.', ['number' => $order->number]) }}

<x-mail::table>
| {{ __('Item') }} | {{ __('Qty') }} | {{ __('Total') }} |
|:-----------------|:---------------:|------------------:|
@foreach($order->items as $item)
| {{ $item->name }} | {{ $item->quantity }} | {{ \App\Support\Money::format($order->prices_include_tax ? $item->line_gross_cents : $item->line_net_cents, $order->currency) }} |
@endforeach
| **{{ __('Total') }}** | | **{{ \App\Support\Money::format($order->total_cents, $order->currency) }}** |
</x-mail::table>

<x-mail::button :url="route('shop.account.orders.show', ['locale' => $order->locale, 'order' => $order])">
{{ __('View order') }}
</x-mail::button>

{{ __('Shipping to') }}:
{{ $order->shipping_address['first_name'] }} {{ $order->shipping_address['last_name'] }}, {{ $order->shipping_address['address1'] }}, {{ $order->shipping_address['zip'] }} {{ $order->shipping_address['city'] }}, {{ \App\Support\Countries::name($order->shipping_address['country'], $order->locale) }}

{{ config('shop.seller.name') }}
</x-mail::message>
