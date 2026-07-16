<?php

use App\Models\Quiz;
use App\Services\Analytics\QuizAnalytics;
use Livewire\Volt\Component;

new class extends Component {
    public Quiz $quiz;

    public function mount(Quiz $quiz): void
    {
        $this->authorize('view', $quiz);

        $this->quiz = $quiz;
    }

    public function with(QuizAnalytics $analytics): array
    {
        $hasVersion = $this->quiz->latestVersion() !== null;

        return [
            'hasVersion' => $hasVersion,
            'summary' => $hasVersion ? $analytics->summary($this->quiz) : null,
            'funnel' => $hasVersion ? $analytics->pageFunnel($this->quiz) : [],
            'questionStats' => $hasVersion ? $analytics->questionStats($this->quiz) : [],
        ];
    }
}; ?>

<section class="w-full">
    <div class="min-w-0">
        <a href="{{ route('quizzes.show', $quiz) }}" wire:navigate class="flex items-center gap-1 text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
            <flux:icon.arrow-left class="size-3.5" />
            {{ $quiz->name }}
        </a>
        <flux:heading size="xl" class="mt-1">{{ __('Analytics') }}</flux:heading>
    </div>

    @unless ($hasVersion)
        <div class="mt-16 flex flex-col items-center justify-center text-center">
            <flux:icon.chart-bar class="size-10 text-zinc-400" />
            <flux:heading class="mt-4">{{ __('No data yet') }}</flux:heading>
            <flux:subheading class="max-w-sm">{{ __('Publish this quiz and analytics will appear as soon as people open it.') }}</flux:subheading>
        </div>
    @else
        @php
            $avg = $summary['avg_seconds'];
            $avgLabel = $avg === null ? '—' : ($avg >= 60 ? intdiv($avg, 60).'m '.($avg % 60).'s' : $avg.'s');
        @endphp

        {{-- KPI tiles --}}
        <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
            @foreach ([
                ['label' => __('Views'), 'value' => number_format($summary['views']), 'icon' => 'eye'],
                ['label' => __('Starts'), 'value' => number_format($summary['starts']), 'icon' => 'play'],
                ['label' => __('Completions'), 'value' => number_format($summary['completions']), 'icon' => 'check-circle'],
                ['label' => __('Completion rate'), 'value' => $summary['completion_rate'] === null ? '—' : $summary['completion_rate'].'%', 'icon' => 'arrow-trending-up'],
                ['label' => __('Avg. time'), 'value' => $avgLabel, 'icon' => 'clock'],
            ] as $tile)
                <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $tile['label'] }}</p>
                        <flux:icon :icon="$tile['icon']" class="size-4 text-zinc-400" />
                    </div>
                    <p class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $tile['value'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Page funnel --}}
        @if (count($funnel) > 1)
            <div class="mt-6 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>{{ __('Page funnel') }}</flux:heading>
                <flux:subheading>{{ __('How far respondents get — of :count who started', ['count' => number_format($summary['starts'])]) }}</flux:subheading>

                <div class="mt-4 space-y-3">
                    @foreach ($funnel as $stage)
                        <div wire:key="funnel-{{ $loop->index }}">
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="truncate text-zinc-700 dark:text-zinc-300">{{ $stage['title'] }}</span>
                                <span class="shrink-0 tabular-nums text-zinc-500 dark:text-zinc-400">
                                    {{ number_format($stage['reached']) }} · {{ $stage['rate'] }}%
                                </span>
                            </div>
                            <div class="mt-1 h-2.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-800" role="img" aria-label="{{ __(':title reached by :rate% of starters', ['title' => $stage['title'], 'rate' => $stage['rate']]) }}">
                                <div class="h-full rounded-full bg-orange-600" style="width: {{ $stage['rate'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Question performance --}}
        <div class="mt-6 space-y-4">
            <flux:heading>{{ __('Question performance') }}</flux:heading>

            @forelse ($questionStats as $stat)
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900" wire:key="qstat-{{ $stat['id'] }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-800 dark:text-white">
                            {{ $stat['title'] !== '' ? $stat['title'] : __('Untitled question') }}
                        </p>
                        <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $stat['type']?->label() }} · {{ trans_choice(':count answer|:count answers', $stat['answered'], ['count' => number_format($stat['answered'])]) }}
                        </span>
                    </div>

                    @if ($stat['distribution'] !== [])
                        <div class="mt-4 space-y-2.5">
                            @foreach ($stat['distribution'] as $row)
                                <div wire:key="qstat-{{ $stat['id'] }}-{{ $loop->index }}">
                                    <div class="flex items-baseline justify-between gap-3 text-sm">
                                        <span class="flex min-w-0 items-center gap-1.5 truncate text-zinc-700 dark:text-zinc-300">
                                            <span class="truncate">{{ $row['label'] }}</span>
                                            @if ($row['is_correct'])
                                                <flux:icon.check-circle class="size-3.5 shrink-0 text-green-600 dark:text-green-400" aria-label="{{ __('Correct answer') }}" />
                                            @endif
                                        </span>
                                        <span class="shrink-0 tabular-nums text-zinc-500 dark:text-zinc-400">
                                            {{ number_format($row['count']) }} · {{ $row['pct'] }}%
                                        </span>
                                    </div>
                                    <div class="mt-1 h-2.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div class="h-full rounded-full bg-orange-600" style="width: {{ $row['pct'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($stat['average'] !== null)
                        <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ __('Average: :value', ['value' => $stat['average']]) }}
                        </p>
                    @endif
                </div>
            @empty
                <flux:text class="text-zinc-500">{{ __('No questions in the published version yet.') }}</flux:text>
            @endforelse
        </div>
    @endunless
</section>
