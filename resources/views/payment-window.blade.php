<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ config('app.name') }}</title>
    </head>
    <body style="font-family: system-ui, sans-serif; padding: 2rem;">
        {{-- The window the checkout opened for the payment provider: it tells
             the checkout to look and closes. Opened any other way, it goes on
             to the page that says how the payment went. --}}
        <p>{{ __('One moment, please …') }}</p>
        <p><a href="{{ $continue }}">{{ __('Continue') }}</a></p>
        <script>
            if (window.opener && !window.opener.closed) {
                window.opener.postMessage({ type: 'raumladen-payment' }, window.location.origin);
                window.close();
            } else {
                window.location.replace(@json($continue));
            }
        </script>
    </body>
</html>
