<?php

use App\Enums\QuizStatus;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $user = Auth::user();
        $workspace = $user->currentWorkspace;

        $counts = Quiz::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $hour = (int) now()->format('G');

        return [
            'greeting' => match (true) {
                $hour < 12 => __('Good morning'),
                $hour < 17 => __('Good afternoon'),
                default => __('Good evening'),
            },
            'firstName' => str($user->name)->before(' '),
            'workspace' => $workspace,
            'totalQuizzes' => $counts->sum(),
            'draftCount' => $counts[QuizStatus::Draft->value] ?? 0,
            'publishedCount' => $counts[QuizStatus::Published->value] ?? 0,
            'memberCount' => $workspace->members()->count(),
            'recentQuizzes' => Quiz::query()->latest('updated_at')->limit(5)->get(),
            'canCreate' => $user->can('create', Quiz::class),
        ];
    }
}; ?>

<section class="w-full">
    {{-- Greeting + primary action --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $greeting }}, {{ $firstName }} 👋</flux:heading>
            <flux:subheading>{{ __('Here\'s what\'s happening in :name.', ['name' => $workspace->name]) }}</flux:subheading>
        </div>

        @if ($canCreate)
            <flux:button :href="route('quizzes.create')" wire:navigate variant="primary" icon="plus">
                {{ __('New quiz') }}
            </flux:button>
        @endif
    </div>

    {{-- Stats --}}
    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['label' => __('Total quizzes'), 'value' => $totalQuizzes, 'icon' => 'puzzle-piece'],
            ['label' => __('Drafts'), 'value' => $draftCount, 'icon' => 'pencil-square'],
            ['label' => __('Published'), 'value' => $publishedCount, 'icon' => 'globe-alt'],
            ['label' => __('Team members'), 'value' => $memberCount, 'icon' => 'users'],
        ] as $stat)
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $stat['label'] }}</p>
                    <span class="flex size-8 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-950/60">
                        <flux:icon :icon="$stat['icon']" class="size-4 text-violet-600 dark:text-violet-400" />
                    </span>
                </div>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Recent quizzes --}}
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 lg:col-span-2">
            <div class="flex items-center justify-between border-b border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading>{{ __('Recent quizzes') }}</flux:heading>
                <flux:button :href="route('quizzes.index')" wire:navigate variant="subtle" size="sm">
                    {{ __('View all') }}
                </flux:button>
            </div>

            @if ($recentQuizzes->isEmpty())
                <div class="flex flex-col items-center justify-center p-10 text-center">
                    <flux:icon.puzzle-piece class="size-8 text-zinc-400" />
                    <flux:heading class="mt-3">{{ __('No quizzes yet') }}</flux:heading>
                    <flux:subheading class="max-w-xs">{{ __('Create your first quiz and it will show up here.') }}</flux:subheading>

                    @if ($canCreate)
                        <flux:button :href="route('quizzes.create')" wire:navigate variant="primary" size="sm" icon="plus" class="mt-4">
                            {{ __('New quiz') }}
                        </flux:button>
                    @endif
                </div>
            @else
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($recentQuizzes as $quiz)
                        <li wire:key="recent-{{ $quiz->id }}">
                            <a href="{{ route('quizzes.show', $quiz) }}" wire:navigate class="flex items-center gap-3 p-4 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                    <flux:icon :icon="$quiz->type->icon()" class="size-4.5 text-zinc-500 dark:text-zinc-400" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-zinc-800 dark:text-white">{{ $quiz->name }}</span>
                                    <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $quiz->type->label() }} &middot; {{ __('Updated :time', ['time' => $quiz->updated_at->diffForHumans()]) }}
                                    </span>
                                </span>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $quiz->status->badgeClasses() }}">
                                    {{ $quiz->status->label() }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Quick actions --}}
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading>{{ __('Quick actions') }}</flux:heading>

            <div class="mt-4 space-y-2">
                @foreach ([
                    ['route' => route('quizzes.create'), 'icon' => 'plus', 'title' => __('Create a quiz'), 'text' => __('Start from one of 14 types')],
                    ['route' => route('settings.members'), 'icon' => 'user-plus', 'title' => __('Invite your team'), 'text' => __('Collaborate in this workspace')],
                    ['route' => route('settings.workspace'), 'icon' => 'cog-6-tooth', 'title' => __('Workspace settings'), 'text' => __('Name, members, and more')],
                ] as $action)
                    <a href="{{ $action['route'] }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-violet-300 hover:bg-violet-50/50 dark:border-zinc-700 dark:hover:border-violet-800 dark:hover:bg-violet-950/30">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-950/60">
                            <flux:icon :icon="$action['icon']" class="size-4.5 text-violet-600 dark:text-violet-400" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-zinc-800 dark:text-white">{{ $action['title'] }}</span>
                            <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $action['text'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>
