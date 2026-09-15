<?php

use App\Models\Quiz;
use App\Services\Analytics\QuizAnalytics;
use Illuminate\Support\Carbon;
use Livewire\Volt\Component;

new class extends Component {
    public Quiz $quiz;

    /** Time window: 7 / 30 / 90 days, or 'all'. */
    public string $range = '30';

    public function mount(Quiz $quiz): void
    {
        $this->authorize('view', $quiz);

        $this->quiz = $quiz;
    }

    /** The catalog of selectable ranges: value => label. */
    public function rangeOptions(): array
    {
        return [
            '7' => __('Last 7 days'),
            '30' => __('Last 30 days'),
            '90' => __('Last 90 days'),
            'all' => __('All time'),
        ];
    }

    protected function since(): ?Carbon
    {
        return $this->range === 'all' ? null : now()->subDays((int) $this->range);
    }

    public function with(QuizAnalytics $analytics): array
    {
        $hasVersion = $this->quiz->latestVersion() !== null;
        $since = $this->since();

        return [
            'hasVersion' => $hasVersion,
            'responseCount' => $this->quiz->responses()->count(),
            'rangeOptions' => $this->rangeOptions(),
            'summary' => $hasVersion ? $analytics->summary($this->quiz, $since) : null,
            'funnel' => $hasVersion ? $analytics->pageFunnel($this->quiz, $since) : [],
            'questionStats' => $hasVersion ? $analytics->questionStats($this->quiz, $since) : [],
        ];
    }
}; ?>

<section class="flex w-full flex-col gap-6">
    <x-page-header
        :title="$quiz->name"
        :back="route('quizzes.index')"
        :back-label="__('Quizzes')"
    >
        <x-slot:meta>
            <x-status-pill :status="$quiz->status" />
            <span>{{ $quiz->type->label() }}</span>
        </x-slot:meta>

        @if ($hasVersion)
            <flux:select wire:model.live="range" class="w-auto min-w-40" :aria-label="__('Time range')">
                @foreach ($rangeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </flux:select>
        @endif

        <x-slot:tabs>
            <x-quiz-nav :quiz="$quiz" :response-count="$responseCount" />
        </x-slot:tabs>
    </x-page-header>

    @unless ($hasVersion)
        <div class="qf-surface">
            <x-empty-state
                icon="chart-bar"
                :title="__('No data yet')"
                :description="__('Publish this quiz and analytics will appear as soon as people open it.')"
            >
                <flux:button :href="route('quizzes.show', $quiz)" wire:navigate variant="primary" icon="globe-alt">
                    {{ __('Publish this quiz') }}
                </flux:button>
            </x-empty-state>
        </div>
    @else
        @php
            $avg = $summary['avg_seconds'];
            $avgLabel = $avg === null ? '—' : ($avg >= 60 ? intdiv($avg, 60).'m '.($avg % 60).'s' : $avg.'s');

            // Drop-off between views and starts is the number people act on.
            $viewToStart = $summary['views'] > 0 ? (int) round($summary['starts'] / $summary['views'] * 100) : null;
        @endphp

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            <x-stat :label="__('Views')" :value="number_format($summary['views'])" icon="eye" :hint="__('Times the quiz was opened')" />
            <x-stat :label="__('Starts')" :value="number_format($summary['starts'])" icon="play" :hint="$viewToStart === null ? __('No views yet') : __(':n% of viewers', ['n' => $viewToStart])" />
            <x-stat :label="__('Completions')" :value="number_format($summary['completions'])" icon="check-circle" :hint="__('Reached the end')" />
            <x-stat :label="__('Completion rate')" :value="$summary['completion_rate'] === null ? '—' : $summary['completion_rate'].'%'" icon="arrow-trending-up" :hint="__('Of everyone who started')" />
            <x-stat :label="__('Average time')" :value="$avgLabel" icon="clock" :hint="__('Start to finish')" />
        </div>

        @if (count($funnel) > 1)
            <x-panel
                :title="__('Page funnel')"
                icon="funnel"
                :description="__('How far respondents get — of :count who started', ['count' => number_format($summary['starts'])])"
            >
                <div class="flex flex-col gap-4">
                    @foreach ($funnel as $stage)
                        @php
                            // Drop from the previous stage is the actionable signal.
                            $previous = $loop->first ? null : $funnel[$loop->index - 1]['reached'];
                            $drop = $previous !== null && $previous > 0
                                ? (int) round(($previous - $stage['reached']) / $previous * 100)
                                : null;
                        @endphp
                        <div wire:key="funnel-{{ $loop->index }}">
                            <div class="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="qf-num inline-flex size-5 shrink-0 items-center justify-center rounded bg-zinc-100 text-[10px] font-bold text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                        {{ $loop->iteration }}
                                    </span>
                                    <span class="truncate font-medium text-zinc-700 dark:text-zinc-300">{{ $stage['title'] }}</span>
                                </span>
                                <span class="flex shrink-0 items-baseline gap-2">
                                    @if ($drop > 0)
                                        <span class="rounded bg-red-50 px-1.5 py-0.5 text-[11px] font-bold text-red-600 dark:bg-red-950/50 dark:text-red-400">
                                            −{{ $drop }}%
                                        </span>
                                    @endif
                                    <span class="qf-num text-zinc-500 dark:text-zinc-400">
                                        {{ number_format($stage['reached']) }} · {{ $stage['rate'] }}%
                                    </span>
                                </span>
                            </div>
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" role="img" aria-label="{{ __(':title reached by :rate% of starters', ['title' => $stage['title'], 'rate' => $stage['rate']]) }}">
                                <div class="h-full rounded-full bg-teal-600 transition-all" style="width: {{ $stage['rate'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-panel>
        @endif

        <div class="flex flex-col gap-4">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="text-base font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('Question performance') }}</h2>
                <span class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ trans_choice(':count question|:count questions', count($questionStats), ['count' => count($questionStats)]) }}
                </span>
            </div>

            @forelse ($questionStats as $stat)
                <x-panel wire:key="qstat-{{ $stat['id'] }}">
                    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                        <p class="min-w-0 flex-1 truncate text-sm font-bold text-zinc-900 dark:text-white">
                            {{ $stat['title'] !== '' ? $stat['title'] : __('Untitled question') }}
                        </p>
                        <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $stat['type']?->label() }}
                            &middot;
                            {{ trans_choice(':count answer|:count answers', $stat['answered'], ['count' => number_format($stat['answered'])]) }}
                        </span>
                    </div>

                    @if ($stat['distribution'] !== [])
                        <div class="flex flex-col gap-3">
                            @foreach ($stat['distribution'] as $row)
                                <div wire:key="qstat-{{ $stat['id'] }}-{{ $loop->index }}">
                                    <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                                        <span class="flex min-w-0 items-center gap-1.5">
                                            <span class="truncate text-zinc-700 dark:text-zinc-300">{{ $row['label'] }}</span>
                                            @if ($row['is_correct'])
                                                <flux:icon.check-circle class="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" aria-label="{{ __('Correct answer') }}" />
                                            @endif
                                        </span>
                                        <span class="qf-num shrink-0 text-zinc-500 dark:text-zinc-400">
                                            {{ number_format($row['count']) }} · {{ $row['pct'] }}%
                                        </span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div @class([
                                            'h-full rounded-full',
                                            'bg-emerald-600' => $row['is_correct'],
                                            'bg-teal-600' => ! $row['is_correct'],
                                        ]) style="width: {{ $row['pct'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($stat['average'] !== null)
                        <div class="qf-well flex items-baseline gap-2 p-4">
                            <span class="qf-num text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $stat['average'] }}</span>
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('average') }}</span>
                        </div>
                    @else
                        <p class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('No answers to summarise yet.') }}</p>
                    @endif
                </x-panel>
            @empty
                <div class="qf-surface">
                    <x-empty-state
                        compact
                        icon="queue-list"
                        :title="__('Nothing to report')"
                        :description="__('The published version has no questions yet.')"
                    />
                </div>
            @endforelse
        </div>
    @endunless
</section>
