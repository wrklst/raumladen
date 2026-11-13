<x-mail::message>
# {{ __('Your login code') }}

<x-mail::panel>
<span style="font-size: 28px; letter-spacing: 6px; font-weight: bold;">{{ $code }}</span>
</x-mail::panel>

{{ __('Enter this code to sign in. It is valid for :minutes minutes. If you did not request it, you can ignore this email.', ['minutes' => $minutes]) }}

{{ config('shop.seller.name') }}
</x-mail::message>
