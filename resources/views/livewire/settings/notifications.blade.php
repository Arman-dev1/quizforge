<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    /** @var array<string, array<string, bool>> */
    public array $preferences = [];

    public function mount(): void
    {
        foreach (User::NOTIFICATION_DEFAULTS as $type => $channels) {
            foreach ($channels as $channel => $default) {
                $this->preferences[$type][$channel] = Auth::user()->wantsNotification($type, $channel);
            }
        }
    }

    public function save(): void
    {
        $clean = [];

        foreach (User::NOTIFICATION_DEFAULTS as $type => $channels) {
            foreach (array_keys($channels) as $channel) {
                $clean[$type][$channel] = (bool) ($this->preferences[$type][$channel] ?? false);
            }
        }

        Auth::user()->forceFill(['notification_preferences' => $clean])->save();

        $this->dispatch('preferences-saved');
    }

    public function with(): array
    {
        return [
            'labels' => [
                'new_response' => ['title' => __('New responses'), 'text' => __('When someone completes one of your quizzes')],
                'new_lead' => ['title' => __('New leads'), 'text' => __('When a respondent leaves their email address')],
                'member_joined' => ['title' => __('Team activity'), 'text' => __('When someone joins your workspace')],
            ],
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="{{ __('Notifications') }}" subheading="{{ __('Choose how you want to hear about activity') }}">
        <form wire:submit="save" class="mt-6 space-y-6">
            <div class="divide-y divide-zinc-100 overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:divide-zinc-800/70 dark:border-zinc-800 dark:bg-zinc-900">
                @foreach ($labels as $type => $label)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4" wire:key="pref-{{ $type }}">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-zinc-900 dark:text-white">{{ $label['title'] }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $label['text'] }}</p>
                        </div>

                        <div class="flex shrink-0 items-center gap-5">
                            <flux:checkbox wire:model="preferences.{{ $type }}.database" label="{{ __('In-app') }}" />
                            <flux:checkbox wire:model="preferences.{{ $type }}.mail" label="{{ __('Email') }}" />
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>

                <x-action-message on="preferences-saved">{{ __('Saved.') }}</x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
