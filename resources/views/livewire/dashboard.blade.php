<?php

use App\Enums\QuizStatus;
use App\Models\Quiz;
use App\Models\QuizResponse;
use App\Services\Billing\UsageLimits;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public function with(UsageLimits $limits): array
    {
        $user = Auth::user();
        $workspace = $user->currentWorkspace;

        $counts = Quiz::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // 8-week response trend for the sparkline.
        $trend = collect(range(7, 0))->map(function (int $weeksAgo) {
            $start = now()->subWeeks($weeksAgo)->startOfWeek();

            return QuizResponse::whereBetween('started_at', [$start, (clone $start)->endOfWeek()])->count();
        })->values();

        $max = max($trend->max(), 1);
        $stepX = 560 / max($trend->count() - 1, 1);
        $points = $trend->map(fn (int $value, int $i) => [
            'x' => round($i * $stepX, 1),
            'y' => round(100 - ($value / $max) * 78, 1),
        ]);

        $line = $points->map(fn ($p, $i) => ($i === 0 ? 'M' : 'L').' '.$p['x'].' '.$p['y'])->implode(' ');
        $area = 'M 0 120 L 0 '.$points->first()['y'].' '.$points->slice(1)->map(fn ($p) => 'L '.$p['x'].' '.$p['y'])->implode(' ').' L 560 120 Z';

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
            'newThisWeek' => Quiz::where('created_at', '>=', now()->subWeek())->count(),
            'draftCount' => $counts[QuizStatus::Draft->value] ?? 0,
            'publishedCount' => $counts[QuizStatus::Published->value] ?? 0,
            'memberCount' => $workspace->members()->count(),
            'memberLimit' => $limits->limit($workspace, 'members'),
            'responsesThisMonth' => $limits->responsesThisMonth($workspace),
            'responseLimit' => $limits->limit($workspace, 'responses_per_month'),
            'trendLine' => $line,
            'trendArea' => $area,
            'trendLast' => $points->last(),
            'recentQuizzes' => Quiz::query()->latest('updated_at')->limit(4)->get(),
            'canCreate' => $user->can('create', Quiz::class),
        ];
    }
}; ?>

<section class="w-full">
    {{-- Greeting + primary action --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" class="tracking-tight">{{ $greeting }}, {{ $firstName }}</flux:heading>
            <flux:subheading>{{ __("Here's what's happening in :name today.", ['name' => $workspace->name]) }}</flux:subheading>
        </div>

        @if ($canCreate)
            <flux:button :href="route('quizzes.create')" wire:navigate variant="primary" icon="plus">
                {{ __('New quiz') }}
            </flux:button>
        @endif
    </div>

    {{-- Stat cards --}}
    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-zinc-500 dark:text-zinc-400">{{ __('Total quizzes') }}</span>
                <flux:icon.puzzle-piece class="size-4 text-teal-600 dark:text-teal-400" />
            </div>
            <div class="mt-3 flex items-end justify-between">
                <span class="text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ $totalQuizzes }}</span>
                @if ($newThisWeek > 0)
                    <span class="rounded-md bg-green-50 px-1.5 py-0.5 text-xs font-bold text-green-700 dark:bg-green-950/60 dark:text-green-400">+{{ $newThisWeek }}</span>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-zinc-500 dark:text-zinc-400">{{ __('Drafts') }}</span>
                <flux:icon.pencil-square class="size-4 text-amber-600 dark:text-amber-400" />
            </div>
            <div class="mt-3 flex items-end justify-between">
                <span class="text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ $draftCount }}</span>
                <span class="font-mono text-xs text-zinc-400">{{ __('in progress') }}</span>
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-zinc-500 dark:text-zinc-400">{{ __('Published') }}</span>
                <flux:icon.globe-alt class="size-4 text-teal-600 dark:text-teal-400" />
            </div>
            <div class="mt-3 flex items-end justify-between">
                <span class="text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ $publishedCount }}</span>
                @if ($publishedCount > 0)
                    <span class="rounded-md bg-green-50 px-1.5 py-0.5 text-xs font-bold text-green-700 dark:bg-green-950/60 dark:text-green-400">{{ __('live') }}</span>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-zinc-500 dark:text-zinc-400">{{ __('Team members') }}</span>
                <flux:icon.users class="size-4 text-teal-600 dark:text-teal-400" />
            </div>
            <div class="mt-3 flex items-end justify-between">
                <span class="text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ $memberCount }}</span>
                <span class="font-mono text-xs text-zinc-400">{{ $memberLimit === null ? __('unlimited') : __('of :n seats', ['n' => $memberLimit]) }}</span>
            </div>
        </div>
    </div>

    {{-- Responses trend + quick actions --}}
    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1.6fr_1fr]">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Responses this month') }}</span>
                <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($responsesThisMonth) }} / {{ $responseLimit === null ? '∞' : number_format($responseLimit) }}</span>
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ number_format($responsesThisMonth) }}</span>
                <span class="text-xs font-semibold text-zinc-400">{{ __('last 8 weeks') }}</span>
            </div>
            <svg viewBox="0 0 560 120" preserveAspectRatio="none" class="mt-4 block h-28 w-full" role="img" aria-label="{{ __('Weekly responses trend') }}">
                <defs>
                    <linearGradient id="dash-trend" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#0d9488" stop-opacity="0.18" />
                        <stop offset="1" stop-color="#0d9488" stop-opacity="0" />
                    </linearGradient>
                </defs>
                <path d="{{ $trendArea }}" fill="url(#dash-trend)" />
                <path d="{{ $trendLine }}" fill="none" stroke="#0d9488" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="dark:[stroke:#2dd4bf]" />
                <circle cx="{{ $trendLast['x'] }}" cy="{{ $trendLast['y'] }}" r="4" fill="#0d9488" class="dark:[fill:#2dd4bf]" />
            </svg>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <span class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Quick actions') }}</span>
            <div class="mt-4 space-y-2">
                @php
                    $actions = [
                        ['route' => route('quizzes.create'), 'icon' => 'plus', 'bg' => 'bg-teal-600 text-white', 'title' => __('Create a quiz'), 'text' => __('Start from 14 types')],
                        ['route' => route('settings.members'), 'icon' => 'user-plus', 'bg' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400', 'title' => __('Invite your team'), 'text' => __('Collaborate here')],
                        ['route' => route('settings.workspace'), 'icon' => 'cog-6-tooth', 'bg' => 'bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400', 'title' => __('Workspace settings'), 'text' => __('Name, members, more')],
                    ];
                @endphp
                @foreach ($actions as $action)
                    <a href="{{ $action['route'] }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-2.5 transition hover:border-teal-300 hover:bg-teal-50/40 dark:border-zinc-800 dark:hover:border-teal-800 dark:hover:bg-teal-950/30">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $action['bg'] }}">
                            <flux:icon :icon="$action['icon']" class="size-4.5" />
                        </span>
                        <span class="min-w-0 leading-tight">
                            <span class="block text-sm font-bold text-zinc-900 dark:text-white">{{ $action['title'] }}</span>
                            <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $action['text'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Recent quizzes --}}
    <div class="mt-4 rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <span class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Recent quizzes') }}</span>
            <flux:link :href="route('quizzes.index')" wire:navigate variant="subtle" class="text-sm font-semibold">{{ __('View all') }}</flux:link>
        </div>

        @if ($recentQuizzes->isEmpty())
            <div class="flex flex-col items-center justify-center p-10 text-center">
                <flux:icon.puzzle-piece class="size-8 text-zinc-400" />
                <flux:heading class="mt-3">{{ __('No quizzes yet') }}</flux:heading>
                <flux:subheading class="max-w-xs">{{ __('Create your first quiz and it will show up here.') }}</flux:subheading>
                @if ($canCreate)
                    <flux:button :href="route('quizzes.create')" wire:navigate variant="primary" size="sm" icon="plus" class="mt-4">{{ __('New quiz') }}</flux:button>
                @endif
            </div>
        @else
            <ul>
                @foreach ($recentQuizzes as $quiz)
                    <li wire:key="recent-{{ $quiz->id }}" class="border-b border-zinc-100 last:border-0 dark:border-zinc-800/70">
                        <a href="{{ route('quizzes.show', $quiz) }}" wire:navigate class="flex items-center gap-3.5 px-5 py-3.5 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <span class="flex size-9.5 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400">
                                <flux:icon :icon="$quiz->type->icon()" class="size-4.5" />
                            </span>
                            <span class="min-w-0 flex-1 leading-tight">
                                <span class="block truncate text-sm font-bold text-zinc-900 dark:text-white">{{ $quiz->name }}</span>
                                <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $quiz->type->label() }} · {{ __('Updated :time', ['time' => $quiz->updated_at->diffForHumans()]) }}
                                </span>
                            </span>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $quiz->status->badgeClasses() }}">
                                {{ $quiz->status->label() }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
