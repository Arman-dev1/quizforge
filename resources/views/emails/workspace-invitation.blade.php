<x-mail::message>
# {{ __('Workspace invitation') }}

{{ __(':inviter has invited you to join the ":workspace" workspace on :app as :role.', [
    'inviter' => $invitation->inviter?->name ?? __('A teammate'),
    'workspace' => $invitation->workspace->name,
    'app' => config('app.name'),
    'role' => $invitation->role->label(),
]) }}

<x-mail::button :url="$acceptUrl">
{{ __('Accept invitation') }}
</x-mail::button>

{{ __("This invitation expires on :date. If you don't have an account yet, you'll be asked to create one with this email address first.", [
    'date' => $invitation->expires_at->toFormattedDateString(),
]) }}

{{ __("If you weren't expecting this invitation, you can ignore this email.") }}

{{ config('app.name') }}
</x-mail::message>
