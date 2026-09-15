<?php

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizResponse;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Quiz $quiz;

    #[Url]
    public string $status = 'all';

    public function mount(Quiz $quiz): void
    {
        $this->authorize('view', $quiz);

        $this->quiz = $quiz;
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function deleteResponse(int $responseId): void
    {
        $this->authorize('update', $this->quiz);

        $this->quiz->responses()->findOrFail($responseId)->delete();
    }

    public function restoreResponse(int $responseId): void
    {
        $this->authorize('update', $this->quiz);

        $this->quiz->responses()->withTrashed()->findOrFail($responseId)->restore();
    }

    public function with(): array
    {
        $responses = $this->quiz->responses()
            ->when($this->status === 'completed', fn ($query) => $query->where('status', QuizResponse::STATUS_COMPLETED))
            ->when($this->status === 'partial', fn ($query) => $query->where('status', QuizResponse::STATUS_IN_PROGRESS))
            ->when($this->status === 'trashed', fn ($query) => $query->onlyTrashed())
            ->latest('started_at')
            ->paginate(15);

        $emails = QuizAnswer::query()
            ->whereIn('quiz_response_id', $responses->pluck('id'))
            ->where('question_type', 'email')
            ->orderBy('id')
            ->get()
            ->groupBy('quiz_response_id')
            ->map(fn ($answers) => $answers->first()->value);

        $total = $this->quiz->responses()->count();
        $completed = $this->quiz->responses()->where('status', QuizResponse::STATUS_COMPLETED)->count();
        $avgScore = $this->quiz->responses()->whereNotNull('percentage')->avg('percentage');

        return [
            'responses' => $responses,
            'emails' => $emails,
            'canManage' => Auth::user()->can('update', $this->quiz),
            'scored' => (bool) ($this->quiz->settings['scored'] ?? false),
            'totalResponses' => $total,
            'completedResponses' => $completed,
            'partialResponses' => $total - $completed,
            'trashedResponses' => $this->quiz->responses()->onlyTrashed()->count(),
            'completionRate' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            'avgScore' => $avgScore !== null ? round($avgScore, 1) : null,
        ];
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
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

        <flux:button href="{{ route('quizzes.responses.export', $quiz) }}" variant="filled" icon="arrow-down-tray">
            {{ __('Export CSV') }}
        </flux:button>

        <x-slot:tabs>
            <x-quiz-nav :quiz="$quiz" :response-count="$totalResponses" />
        </x-slot:tabs>
    </x-page-header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat :label="__('Total responses')" :value="number_format($totalResponses)" icon="inbox" />
        <x-stat :label="__('Completed')" :value="number_format($completedResponses)" icon="check-circle" :hint="__(':n% completion rate', ['n' => $completionRate])" />
        <x-stat :label="__('Partial')" :value="number_format($partialResponses)" icon="clock" :hint="__('Started but not finished')" />
        <x-stat
            :label="__('Average score')"
            :value="$avgScore === null ? '—' : $avgScore . '%'"
            icon="academic-cap"
            :hint="$scored ? __('Across scored responses') : __('Scoring is off for this quiz')"
        />
    </div>

    {{-- Filter as tabs with counts: the counts are the reason you'd switch. --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="qf-scroll-x -mb-px flex items-center gap-5 border-b border-zinc-200 dark:border-zinc-800">
            @php
                $filters = [
                    ['key' => 'all', 'label' => __('All'), 'count' => $totalResponses],
                    ['key' => 'completed', 'label' => __('Completed'), 'count' => $completedResponses],
                    ['key' => 'partial', 'label' => __('Partial'), 'count' => $partialResponses],
                    ['key' => 'trashed', 'label' => __('Trash'), 'count' => $trashedResponses],
                ];
            @endphp

            @foreach ($filters as $filter)
                <button
                    type="button"
                    wire:click="setStatus('{{ $filter['key'] }}')"
                    @class(['qf-tab', 'qf-tab-active' => $status === $filter['key']])
                >
                    {{ $filter['label'] }}
                    @if ($filter['count'] > 0)
                        <span class="qf-num rounded-full bg-zinc-100 px-1.5 text-[11px] font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $filter['count'] }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    @if ($responses->isEmpty())
        <div class="qf-surface">
            @if ($status === 'all')
                <x-empty-state
                    icon="inbox"
                    :title="__('No responses yet')"
                    :description="__('Share your quiz link and responses will land here as people answer — including partial ones.')"
                >
                    @if ($quiz->status === App\Enums\QuizStatus::Published)
                        <flux:button :href="route('quizzes.show', $quiz)" wire:navigate variant="primary" icon="share">
                            {{ __('Get the share link') }}
                        </flux:button>
                    @else
                        <flux:button :href="route('quizzes.show', $quiz)" wire:navigate variant="primary" icon="globe-alt">
                            {{ __('Publish this quiz') }}
                        </flux:button>
                    @endif
                </x-empty-state>
            @else
                <x-empty-state
                    icon="funnel"
                    :title="__('Nothing here')"
                    :description="__('No responses match this filter yet.')"
                >
                    <flux:button wire:click="setStatus('all')" variant="filled">{{ __('Show all responses') }}</flux:button>
                </x-empty-state>
            @endif
        </div>
    @else
        <div class="qf-surface overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-160 text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50/60 text-left dark:border-zinc-800 dark:bg-zinc-950/30">
                        <tr>
                            <th class="qf-eyebrow px-5 py-3 font-semibold">{{ __('Respondent') }}</th>
                            <th class="qf-eyebrow px-5 py-3 font-semibold">{{ __('Status') }}</th>
                            @if ($scored)
                                <th class="qf-eyebrow px-5 py-3 font-semibold">{{ __('Score') }}</th>
                            @endif
                            <th class="qf-eyebrow px-5 py-3 font-semibold">{{ __('Started') }}</th>
                            <th class="qf-eyebrow px-5 py-3 font-semibold">{{ __('Duration') }}</th>
                            <th class="px-5 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($responses as $response)
                            <tr wire:key="response-{{ $response->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('quizzes.responses.show', [$quiz, $response->id]) }}" wire:navigate class="font-bold text-zinc-900 hover:text-teal-700 dark:text-white dark:hover:text-teal-400">
                                        {{ $emails[$response->id] ?? __('Response #:id', ['id' => $response->id]) }}
                                    </a>
                                </td>

                                <td class="px-5 py-3.5">
                                    <span @class([
                                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold',
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' => $response->isCompleted(),
                                        'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400' => ! $response->isCompleted(),
                                    ])>
                                        <span @class([
                                            'size-1.5 rounded-full',
                                            'bg-emerald-500' => $response->isCompleted(),
                                            'bg-amber-500' => ! $response->isCompleted(),
                                        ])></span>
                                        {{ $response->isCompleted() ? __('Completed') : __('Partial') }}
                                    </span>
                                </td>

                                @if ($scored)
                                    <td class="px-5 py-3.5">
                                        @if ($response->score !== null)
                                            <div class="flex items-center gap-2.5">
                                                <span class="qf-num w-14 text-sm font-bold text-zinc-900 dark:text-white">{{ $response->score }}/{{ $response->max_score }}</span>
                                                <span class="h-1.5 w-16 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                                    <span @class([
                                                        'block h-full rounded-full',
                                                        'bg-emerald-500' => $response->passed,
                                                        'bg-red-500' => $response->passed === false,
                                                        'bg-teal-600' => $response->passed === null,
                                                    ]) style="width: {{ (int) min(100, max(0, $response->percentage ?? 0)) }}%"></span>
                                                </span>
                                                <span class="qf-num text-xs text-zinc-400">{{ $response->percentage }}%</span>
                                            </div>
                                        @else
                                            <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                        @endif
                                    </td>
                                @endif

                                <td class="px-5 py-3.5 text-zinc-600 dark:text-zinc-300">{{ $response->started_at->diffForHumans() }}</td>

                                <td class="qf-num px-5 py-3.5 text-zinc-500 dark:text-zinc-400">
                                    {{ $response->completed_at ? $response->started_at->shortAbsoluteDiffForHumans($response->completed_at) : '—' }}
                                </td>

                                <td class="px-5 py-3.5 text-right">
                                    @if ($canManage)
                                        @if ($response->trashed())
                                            <flux:button variant="subtle" size="sm" icon="arrow-uturn-left" wire:click="restoreResponse({{ $response->id }})" aria-label="{{ __('Restore response') }}" />
                                        @else
                                            <x-confirm
                                                action="deleteResponse({{ $response->id }})"
                                                :title="__('Move to trash?')"
                                                :description="__('This response moves to the trash. You can restore it from the Trash filter.')"
                                                :confirm="__('Move to trash')"
                                                icon="trash"
                                            >
                                                <x-slot:trigger>
                                                    <flux:button variant="subtle" size="sm" icon="trash" aria-label="{{ __('Delete response') }}" />
                                                </x-slot:trigger>
                                            </x-confirm>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($responses->hasPages())
            <div>{{ $responses->links() }}</div>
        @endif
    @endif
</section>
