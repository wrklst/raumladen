<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@if($url['lastmod'])
        <lastmod>{{ $url['lastmod'] }}</lastmod>
@endif
@foreach($url['alternates'] as $lang => $href)
        <xhtml:link rel="alternate" hreflang="{{ $lang }}" href="{{ $href }}"/>
@endforeach
@if(isset($url['alternates'][$default]))
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $url['alternates'][$default] }}"/>
@endif
    </url>
@endforeach
</urlset>
