<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function open(string $notificationId): void
    {
        $notification = Auth::user()->notifications()->findOrFail($notificationId);

        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('dashboard'), navigate: true);
    }

    public function with(): array
    {
        return [
            'notifications' => Auth::user()->notifications()->limit(10)->get(),
            'unreadCount' => Auth::user()->unreadNotifications()->count(),
        ];
    }
}; ?>

<div wire:poll.60s>
    <flux:dropdown position="bottom" align="end">
        <button
            type="button"
            class="relative flex size-9 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-200/60 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
            aria-label="{{ $unreadCount > 0 ? __(':count unread notifications', ['count' => $unreadCount]) : __('Notifications') }}"
        >
            <flux:icon.bell class="size-5" />
            @if ($unreadCount > 0)
                <span class="absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-orange-600 px-1 text-[10px] font-bold leading-4 text-white">
                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                </span>
            @endif
        </button>

        <flux:menu class="w-[320px]">
            <div class="flex items-center justify-between px-2 py-1.5">
                <p class="text-sm font-semibold text-zinc-800 dark:text-white">{{ __('Notifications') }}</p>
                @if ($unreadCount > 0)
                    <button type="button" wire:click="markAllRead" class="text-xs text-orange-700 hover:underline dark:text-orange-400">
                        {{ __('Mark all read') }}
                    </button>
                @endif
            </div>

            <flux:menu.separator />

            @forelse ($notifications as $notification)
                <button
                    type="button"
                    wire:click="open('{{ $notification->id }}')"
                    wire:key="notification-{{ $notification->id }}"
                    class="flex w-full items-start gap-3 rounded-lg px-2 py-2 text-left transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60"
                >
                    <span @class([
                        'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg',
                        'bg-orange-50 dark:bg-orange-950/60' => $notification->unread(),
                        'bg-zinc-100 dark:bg-zinc-800' => ! $notification->unread(),
                    ])>
                        <flux:icon :icon="$notification->data['icon'] ?? 'bell'" @class([
                            'size-4',
                            'text-orange-600 dark:text-orange-400' => $notification->unread(),
                            'text-zinc-400' => ! $notification->unread(),
                        ]) />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span @class([
                            'block text-sm leading-snug',
                            'font-medium text-zinc-800 dark:text-white' => $notification->unread(),
                            'text-zinc-500 dark:text-zinc-400' => ! $notification->unread(),
                        ])>
                            {{ $notification->data['message'] ?? '' }}
                        </span>
                        <span class="block text-xs text-zinc-400">{{ $notification->created_at->diffForHumans() }}</span>
                    </span>
                    @if ($notification->unread())
                        <span class="mt-2 size-2 shrink-0 rounded-full bg-orange-600" aria-hidden="true"></span>
                    @endif
                </button>
            @empty
                <div class="px-2 py-6 text-center">
                    <flux:icon.bell-slash class="mx-auto size-6 text-zinc-300 dark:text-zinc-600" />
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Nothing yet — new responses and leads will show up here.') }}</p>
                </div>
            @endforelse
        </flux:menu>
    </flux:dropdown>
</div>
