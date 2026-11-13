<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
<channel>
    <title>{{ config('shop.seller.name') }}</title>
    <link>{{ route('shop.home', ['locale' => $locale]) }}</link>
    <description>{{ config('shop.seller.name') }}</description>
@foreach($items as $item)
    <item>
        <g:id>{{ $item['id'] }}</g:id>
        <g:title>{{ $item['title'] }}</g:title>
        <g:description>{{ \Illuminate\Support\Str::limit(strip_tags($item['description']), 5000, '') }}</g:description>
        <g:link>{{ $item['link'] }}</g:link>
@if($item['image_link'])
        <g:image_link>{{ $item['image_link'] }}</g:image_link>
@endif
        <g:availability>{{ $item['availability'] }}</g:availability>
        <g:price>{{ $item['price'] }}</g:price>
        <g:condition>new</g:condition>
        <g:brand>{{ $item['brand'] }}</g:brand>
@if($item['gtin'])
        <g:gtin>{{ $item['gtin'] }}</g:gtin>
@else
        <g:identifier_exists>no</g:identifier_exists>
@endif
        <g:google_product_category>{{ $item['google_product_category'] }}</g:google_product_category>
@if($item['shipping_weight'])
        <g:shipping_weight>{{ $item['shipping_weight'] }}</g:shipping_weight>
@endif
@if($shipping)
        <g:shipping>
            <g:country>{{ $shipping['country'] }}</g:country>
            <g:price>{{ $shipping['price'] }}</g:price>
        </g:shipping>
@endif
    </item>
@endforeach
</channel>
</rss>
