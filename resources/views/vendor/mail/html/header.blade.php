@props(['url'])
{{-- The head of every email: the shop's logo if one is set (Shop settings, Appearance), its name otherwise. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if($logo = \App\Settings\Appearance::logoUrl())
<img src="{{ $logo }}" alt="{{ config('app.name') }}" style="max-height: 60px; max-width: 240px; width: auto; height: auto;">
@else
{{ config('app.name') }}
@endif
</a>
</td>
</tr>
