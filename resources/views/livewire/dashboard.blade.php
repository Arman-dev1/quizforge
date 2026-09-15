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
        $monthStart = now()->startOfMonth();

        $counts = Quiz::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $monthResponses = QuizResponse::query()->where('started_at', '>=', $monthStart);

        $started = (clone $monthResponses)->count();
        $completed = (clone $monthResponses)->where('status', QuizResponse::STATUS_COMPLETED)->count();

        $leadsThisMonth = (clone $monthResponses)
            ->whereHas('answers', fn ($query) => $query->where('question_type', 'email'))
            ->count();

        // 8-week trend, one grouped query rather than eight counts.
        $trendStart = now()->subWeeks(7)->startOfWeek();
        $weekly = QuizResponse::query()
            ->where('started_at', '>=', $trendStart)
            ->get(['started_at'])
            ->groupBy(fn (QuizResponse $r) => $r->started_at->startOfWeek()->toDateString())
            ->map->count();

        $trend = collect(range(7, 0))->map(function (int $weeksAgo) use ($weekly) {
            $start = now()->subWeeks($weeksAgo)->startOfWeek();

            return [
                'label' => $start->format('M j'),
                'value' => (int) ($weekly[$start->toDateString()] ?? 0),
            ];
        })->values();

        $max = max($trend->max('value'), 1);
        $stepX = 560 / max($trend->count() - 1, 1);
        $points = $trend->map(fn (array $week, int $i) => [
            'x' => round($i * $stepX, 1),
            'y' => round(104 - ($week['value'] / $max) * 84, 1),
            'label' => $week['label'],
            'value' => $week['value'],
        ]);

        $line = $points->map(fn ($p, $i) => ($i === 0 ? 'M' : 'L').' '.$p['x'].' '.$p['y'])->implode(' ');
        $area = 'M 0 120 L 0 '.$points->first()['y'].' '.$points->slice(1)->map(fn ($p) => 'L '.$p['x'].' '.$p['y'])->implode(' ').' L 560 120 Z';

        $hour = (int) now()->format('G');

        // Published quizzes with no responses at all are the actionable
        // thing on this screen: live, but nobody has seen them.
        $needsAttention = Quiz::query()
            ->where('status', QuizStatus::Published)
            ->whereDoesntHave('responses')
            ->latest('published_at')
            ->limit(3)
            ->get();

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
            'responsesThisMonth' => $started,
            'responseLimit' => $limits->limit($workspace, 'responses_per_month'),
            'completionRate' => $started > 0 ? (int) round($completed / $started * 100) : null,
            'completedThisMonth' => $completed,
            'leadsThisMonth' => $leadsThisMonth,
            'trendPoints' => $points,
            'trendLine' => $line,
            'trendArea' => $area,
            'trendTotal' => $trend->sum('value'),
            'needsAttention' => $needsAttention,
            'recentQuizzes' => Quiz::query()->withCount('responses')->latest('updated_at')->limit(5)->get(),
            'canCreate' => $user->can('create', Quiz::class),
        ];
    }
}; ?>

<section class="flex w-full flex-col gap-6">
    <x-page-header
        :title="$greeting . ', ' . $firstName"
        :description="__('Here\'s what\'s happening in :name.', ['name' => $workspace->name])"
    >
        <flux:button :href="route('quizzes.index')" wire:navigate variant="filled" icon="puzzle-piece">
            {{ __('All quizzes') }}
        </flux:button>
        @if ($canCreate)
            <flux:button :href="route('quizzes.create')" wire:navigate variant="primary" icon="plus">
                {{ __('New quiz') }}
            </flux:button>
        @endif
    </x-page-header>

    {{-- Four distinct measures. Drafts vs. published used to take two tiles
         to say one thing; that now lives under "Live quizzes". --}}
    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-stat
            :label="__('Responses this month')"
            :value="number_format($responsesThisMonth)"
            :limit="$responseLimit"
            icon="inbox"
            :hint="__(':n completed', ['n' => number_format($completedThisMonth)])"
        />
        <x-stat
            :label="__('Completion rate')"
            :value="$completionRate === null ? '—' : $completionRate . '%'"
            icon="check-circle"
            :hint="$completionRate === null ? __('No responses yet this month') : __('of everyone who started')"
        />
        <x-stat
            :label="__('Leads captured')"
            :value="number_format($leadsThisMonth)"
            icon="user-plus"
            :hint="__('This month, incl. partials')"
            :href="route('leads.index')"
        />
        <x-stat
            :label="__('Live quizzes')"
            :value="number_format($publishedCount)"
            icon="globe-alt"
            :hint="$draftCount > 0 ? __(':n in draft', ['n' => $draftCount]) : __('of :n total', ['n' => $totalQuizzes])"
            :href="route('quizzes.index')"
        />
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1.7fr_1fr]">
        {{-- Trend, with the axis labelled — a sparkline with no time scale
             tells you a shape but not when anything happened. --}}
        <x-panel :title="__('Responses')" :description="__('Weekly, last 8 weeks')">
            <x-slot:actions>
                <span class="qf-num text-sm font-bold text-zinc-900 dark:text-white">{{ number_format($trendTotal) }}</span>
                <span class="text-xs text-zinc-400">{{ __('total') }}</span>
            </x-slot:actions>

            @if ($trendTotal === 0)
                <x-empty-state
                    compact
                    icon="chart-bar"
                    :title="__('No responses yet')"
                    :description="__('Publish a quiz and share its link — responses will chart here as they arrive.')"
                />
            @else
                <svg viewBox="0 0 560 120" preserveAspectRatio="none" class="block h-32 w-full" role="img" aria-label="{{ __('Weekly responses, last 8 weeks') }}">
                    <defs>
                        <linearGradient id="dash-trend" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#0d9488" stop-opacity="0.16" />
                            <stop offset="1" stop-color="#0d9488" stop-opacity="0" />
                        </linearGradient>
                    </defs>

                    {{-- Faint baseline grid so the line has something to sit against. --}}
                    @foreach ([20, 62, 104] as $y)
                        <line x1="0" y1="{{ $y }}" x2="560" y2="{{ $y }}" stroke="currentColor" stroke-width="1" class="text-zinc-200 dark:text-zinc-800" vector-effect="non-scaling-stroke" />
                    @endforeach

                    <path d="{{ $trendArea }}" fill="url(#dash-trend)" />
                    <path d="{{ $trendLine }}" fill="none" stroke="#0d9488" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" class="dark:[stroke:#2dd4bf]" />
                    <circle cx="{{ $trendPoints->last()['x'] }}" cy="{{ $trendPoints->last()['y'] }}" r="4" fill="#0d9488" class="dark:[fill:#2dd4bf]" />
                </svg>

                <div class="mt-2 flex justify-between">
                    @foreach ($trendPoints as $i => $point)
                        <span @class([
                            'text-[10px] font-medium text-zinc-400 dark:text-zinc-500',
                            'max-sm:hidden' => $i % 2 !== 0,
                        ])>{{ $point['label'] }}</span>
                    @endforeach
                </div>
            @endif
        </x-panel>

        {{-- Replaces "Quick actions" (which duplicated the sidebar) with
             something only this screen can tell you. --}}
        <x-panel :title="__('Needs attention')" icon="exclamation-triangle">
            @if ($needsAttention->isEmpty())
                <div class="flex h-full flex-col items-center justify-center gap-3 py-8 text-center">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                        <flux:icon.check class="size-5" />
                    </span>
                    <div>
                        <p class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('All clear') }}</p>
                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Every published quiz has responses.') }}</p>
                    </div>
                </div>
            @else
                <p class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Published, but no responses yet — they may need sharing.') }}
                </p>
                <ul class="flex flex-col gap-2">
                    @foreach ($needsAttention as $quiz)
                        <li wire:key="attention-{{ $quiz->id }}">
                            <a href="{{ route('quizzes.show', $quiz) }}" wire:navigate class="qf-well flex items-center gap-3 p-2.5 transition hover:border-teal-300 dark:hover:border-teal-800">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                                    <flux:icon.paper-airplane class="size-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $quiz->name }}</span>
                                    <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Live :time', ['time' => $quiz->published_at?->diffForHumans() ?? '']) }}
                                    </span>
                                </span>
                                <flux:icon.chevron-right class="size-4 shrink-0 text-zinc-300 dark:text-zinc-600" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-panel>
    </div>

    <x-panel :title="__('Recent quizzes')" flush>
        <x-slot:actions>
            <flux:link :href="route('quizzes.index')" wire:navigate variant="subtle" class="text-sm font-semibold">
                {{ __('View all') }}
            </flux:link>
        </x-slot:actions>

        @if ($recentQuizzes->isEmpty())
            <x-empty-state
                icon="puzzle-piece"
                :title="__('No quizzes yet')"
                :description="__('Build your first quiz, publish it, and start collecting responses.')"
            >
                @if ($canCreate)
                    <flux:button :href="route('quizzes.create')" wire:navigate variant="primary" icon="plus">
                        {{ __('Create a quiz') }}
                    </flux:button>
                @endif
            </x-empty-state>
        @else
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($recentQuizzes as $quiz)
                    <li wire:key="recent-{{ $quiz->id }}">
                        <a href="{{ route('quizzes.show', $quiz) }}" wire:navigate class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400">
                                <flux:icon :icon="$quiz->type->icon()" class="size-4.5" />
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold text-zinc-900 dark:text-white">{{ $quiz->name }}</span>
                                <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $quiz->type->label() }} · {{ __('Updated :time', ['time' => $quiz->updated_at->diffForHumans()]) }}
                                </span>
                            </span>

                            <span class="hidden text-right sm:block">
                                <span class="qf-num block text-sm font-bold text-zinc-900 dark:text-white">{{ number_format($quiz->responses_count) }}</span>
                                <span class="block text-[11px] text-zinc-400">{{ __('responses') }}</span>
                            </span>

                            <x-status-pill :status="$quiz->status" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-panel>
</section>
