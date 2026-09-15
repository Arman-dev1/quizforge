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

    <x-settings.layout
        :heading="__('Notifications')"
        :subheading="__('Choose what reaches you, and where.')"
    >
        <form wire:submit="save" class="flex flex-col gap-5">
            <x-panel :title="__('Activity')" icon="bell" flush>
                {{-- Column headings, so the two unlabelled checkbox columns
                     aren't a guess on every row. --}}
                <div class="hidden items-center gap-3 border-b border-zinc-200 bg-zinc-50/60 px-5 py-2.5 sm:flex dark:border-zinc-800 dark:bg-zinc-950/30">
                    <span class="qf-eyebrow flex-1">{{ __('Notify me about') }}</span>
                    <span class="qf-eyebrow w-16 text-center">{{ __('In-app') }}</span>
                    <span class="qf-eyebrow w-16 text-center">{{ __('Email') }}</span>
                </div>

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($labels as $type => $label)
                        <div class="flex flex-wrap items-center gap-3 px-5 py-4" wire:key="pref-{{ $type }}">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-zinc-900 dark:text-white">{{ $label['title'] }}</p>
                                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $label['text'] }}</p>
                            </div>

                            <div class="flex shrink-0 items-center gap-5 sm:gap-0">
                                <div class="flex items-center gap-2 sm:w-16 sm:justify-center sm:gap-0">
                                    <flux:checkbox wire:model="preferences.{{ $type }}.database" :aria-label="__('In-app notifications for :type', ['type' => $label['title']])" />
                                    <span class="text-xs text-zinc-500 sm:hidden">{{ __('In-app') }}</span>
                                </div>
                                <div class="flex items-center gap-2 sm:w-16 sm:justify-center sm:gap-0">
                                    <flux:checkbox wire:model="preferences.{{ $type }}.mail" :aria-label="__('Email notifications for :type', ['type' => $label['title']])" />
                                    <span class="text-xs text-zinc-500 sm:hidden">{{ __('Email') }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-panel>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save preferences') }}</flux:button>
                <x-action-message on="preferences-saved">{{ __('Saved.') }}</x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
