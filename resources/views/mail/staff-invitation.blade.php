<x-mail::message>
# {{ __('Hello :name', ['name' => $user->name]) }}

{{ __(':inviter invites you to work in the backend of :shop as :role.', ['inviter' => $invitedBy->name, 'shop' => config('app.name'), 'role' => $user->role->label()]) }}

<x-mail::button :url="$url">
{{ __('Accept invitation and set password') }}
</x-mail::button>

{{ __('The link is valid for :days days. If you did not expect this invitation, you can ignore this email.', ['days' => $days]) }}

{{ config('app.name') }}
</x-mail::message>
