<div class="flex items-start max-md:flex-col">
    <div class="mr-10 w-full pb-4 md:w-[220px]">
        <flux:navlist>
            <flux:navlist.item href="{{ route('settings.profile') }}" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            <flux:navlist.item href="{{ route('settings.password') }}" wire:navigate>{{ __('Password') }}</flux:navlist.item>
            <flux:navlist.item href="{{ route('settings.appearance') }}" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
            <flux:navlist.item href="{{ route('settings.notifications') }}" wire:navigate>{{ __('Notifications') }}</flux:navlist.item>
            <flux:navlist.item href="{{ route('settings.workspace') }}" wire:navigate>{{ __('Workspace') }}</flux:navlist.item>
            <flux:navlist.item href="{{ route('settings.members') }}" wire:navigate>{{ __('Members') }}</flux:navlist.item>
            @if (auth()->user()->currentWorkspace && auth()->user()->roleIn(auth()->user()->currentWorkspace) === \App\Enums\WorkspaceRole::Owner)
                <flux:navlist.item href="{{ route('settings.billing') }}" wire:navigate>{{ __('Billing') }}</flux:navlist.item>
            @endif
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading class="tracking-tight">{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-2xl">
            {{ $slot }}
        </div>
    </div>
</div>
