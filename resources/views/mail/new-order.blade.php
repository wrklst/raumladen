<x-mail::message>
# {{ __('New order :number', ['number' => $order->number]) }}

{{ $order->customerName() }}, {{ $order->email }}

<x-mail::table>
| {{ __('Item') }} | {{ __('Qty') }} | {{ __('Total') }} |
|:-----------------|:---------------:|------------------:|
@foreach($order->items as $item)
| {{ $item->name }} | {{ $item->quantity }} | {{ \App\Support\Money::format($order->prices_include_tax ? $item->line_gross_cents : $item->line_net_cents, $order->currency) }} |
@endforeach
| **{{ __('Total') }}** | | **{{ \App\Support\Money::format($order->total_cents, $order->currency) }}** |
</x-mail::table>

{{ $order->isPaid() ? __('Paid: the order can be shipped.') : __('Not paid yet: the customer transfers the amount.') }}

{{ __('Shipping to') }}:
{{ $order->shipping_address['first_name'] }} {{ $order->shipping_address['last_name'] }}, {{ $order->shipping_address['address1'] }}, {{ $order->shipping_address['zip'] }} {{ $order->shipping_address['city'] }}, {{ \App\Support\Countries::name($order->shipping_address['country']) }}

<x-mail::button :url="route('admin.orders.show', $order)">
{{ __('Open the order') }}
</x-mail::button>
</x-mail::message>
