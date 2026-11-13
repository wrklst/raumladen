<x-mail::message>
# {{ __('Your order is on its way') }}

{{ __('Order :number has been shipped to:', ['number' => $order->number]) }}

{{ $order->shipping_address['first_name'] }} {{ $order->shipping_address['last_name'] }}
{{ $order->shipping_address['address1'] }}
{{ $order->shipping_address['zip'] }} {{ $order->shipping_address['city'] }}
{{ \App\Support\Countries::name($order->shipping_address['country'], $order->locale) }}

<x-mail::button :url="route('shop.account.orders.show', ['locale' => $order->locale, 'order' => $order])">
{{ __('View order') }}
</x-mail::button>

@include('mail.signature')
</x-mail::message>
