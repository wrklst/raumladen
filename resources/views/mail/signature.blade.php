{{-- The closing lines of every email (Shop settings, Appearance). A theme may replace this partial. --}}
{!! nl2br(e(\App\Settings\Appearance::mailSignature())) !!}
