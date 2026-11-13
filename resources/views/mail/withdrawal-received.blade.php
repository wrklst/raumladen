<x-mail::message>
# {{ __('We have received your withdrawal') }}

{{ __('Received on :date at :time.', ['date' => $withdrawal->created_at->timezone('Europe/Berlin')->format('d.m.Y'), 'time' => $withdrawal->created_at->timezone('Europe/Berlin')->format('H:i').' (Europe/Berlin)']) }}

**{{ __('Order no.') }}:** {{ $withdrawal->order_number }}
**{{ __('Name') }}:** {{ $withdrawal->name }}
**{{ __('Email address') }}:** {{ $withdrawal->email }}

@if($withdrawal->statement)
**{{ __('Your statement') }}:**
{{ $withdrawal->statement }}
@endif

{{ __('Please send the goods back within fourteen days to:') }}
{{ config('shop.seller.name') }}, {{ config('shop.seller.address1') }}, {{ config('shop.seller.zip') }} {{ config('shop.seller.city') }}

{{ __('We refund your payment within fourteen days, as described in the withdrawal information.') }}

{{ config('shop.seller.name') }}
</x-mail::message>
