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
            'completionRate' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            'avgScore' => $avgScore !== null ? round($avgScore, 1) : null,
        ];
    }
}; ?>

<section class="w-full">
    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('quizzes.show', $quiz) }}" wire:navigate class="flex items-center gap-1.5 text-xs font-semibold text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                <flux:icon.arrow-left class="size-3.5" />
                {{ $quiz->name }}
            </a>
            <flux:heading size="xl" class="mt-1 tracking-tight">{{ __('Responses') }}</flux:heading>
            <flux:subheading>
                {{ trans_choice(':count completed|:count completed', $completedResponses, ['count' => $completedResponses]) }}
                @if ($avgScore !== null)
                    · {{ __('avg score :n%', ['n' => $avgScore]) }}
                @endif
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2.5">
            <flux:select wire:model.live="status" class="w-40" aria-label="{{ __('Filter by status') }}">
                <option value="all">{{ __('All responses') }}</option>
                <option value="completed">{{ __('Completed') }}</option>
                <option value="partial">{{ __('Partial') }}</option>
                <option value="trashed">{{ __('Trashed') }}</option>
            </flux:select>

            <flux:button href="{{ route('quizzes.responses.export', $quiz) }}" variant="primary" icon="arrow-down-tray">
                {{ __('Export CSV') }}
            </flux:button>
        </div>
    </div>

    {{-- Stat tiles --}}
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ __('Total responses') }}</div>
            <div class="mt-2 text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ number_format($totalResponses) }}</div>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ __('Completion rate') }}</div>
            <div class="mt-2 text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ $completionRate }}<span class="text-base text-zinc-400">%</span></div>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ __('Avg. score') }}</div>
            <div class="mt-2 text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">
                @if ($avgScore !== null){{ $avgScore }}<span class="text-base text-zinc-400">%</span>@else<span class="text-zinc-400">—</span>@endif
            </div>
        </div>
    </div>

    @if ($responses->isEmpty())
        <div class="mt-16 flex flex-col items-center justify-center text-center">
            <flux:icon.inbox class="size-10 text-zinc-400" />
            <flux:heading class="mt-4">
                {{ $status === 'all' ? __('No responses yet') : __('No responses match this filter') }}
            </flux:heading>
            <flux:subheading class="max-w-sm">
                {{ $status === 'all' ? __('Share your quiz link and responses will land here in real time.') : __('Try a different filter.') }}
            </flux:subheading>
        </div>
    @else
        <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800">
            <div class="overflow-x-auto">
                <table class="w-full min-w-160 text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <tr>
                            <th class="px-5 py-3">{{ __('Respondent') }}</th>
                            <th class="px-5 py-3">{{ __('Status') }}</th>
                            @if ($scored)
                                <th class="px-5 py-3">{{ __('Score') }}</th>
                            @endif
                            <th class="px-5 py-3">{{ __('Started') }}</th>
                            <th class="px-5 py-3">{{ __('Duration') }}</th>
                            <th class="px-5 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/70">
                        @foreach ($responses as $response)
                            <tr wire:key="response-{{ $response->id }}" class="bg-white dark:bg-zinc-900">
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('quizzes.responses.show', [$quiz, $response->id]) }}" wire:navigate class="font-bold text-zinc-900 hover:underline dark:text-white">
                                        {{ $emails[$response->id] ?? __('Response #:id', ['id' => $response->id]) }}
                                    </a>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span @class([
                                        'rounded-full border px-2.5 py-0.5 text-xs font-semibold',
                                        'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950/60 dark:text-green-300' => $response->isCompleted(),
                                        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300' => ! $response->isCompleted(),
                                    ])>
                                        {{ $response->isCompleted() ? __('Completed') : __('Partial') }}
                                    </span>
                                </td>
                                @if ($scored)
                                    <td class="px-5 py-3.5">
                                        @if ($response->score !== null)
                                            <div class="flex items-center gap-2.5">
                                                <span class="font-mono text-sm font-bold text-zinc-900 dark:text-white">{{ $response->score }}/{{ $response->max_score }}</span>
                                                <span class="h-1.5 w-16 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                                    <span @class([
                                                        'block h-full',
                                                        'bg-green-500' => $response->passed,
                                                        'bg-amber-500' => $response->passed === false,
                                                        'bg-teal-500' => $response->passed === null,
                                                    ]) style="width: {{ (int) min(100, max(0, $response->percentage ?? 0)) }}%"></span>
                                                </span>
                                                <span class="font-mono text-xs text-zinc-400">{{ $response->percentage }}%</span>
                                            </div>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-5 py-3.5 text-zinc-600 dark:text-zinc-300">{{ $response->started_at->diffForHumans() }}</td>
                                <td class="px-5 py-3.5 font-mono text-zinc-500 dark:text-zinc-400">
                                    {{ $response->completed_at ? $response->started_at->shortAbsoluteDiffForHumans($response->completed_at) : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    @if ($canManage)
                                        @if ($response->trashed())
                                            <flux:button variant="subtle" size="sm" icon="arrow-uturn-left" wire:click="restoreResponse({{ $response->id }})" aria-label="{{ __('Restore response') }}" />
                                        @else
                                            <flux:button
                                                variant="subtle"
                                                size="sm"
                                                icon="trash"
                                                wire:click="deleteResponse({{ $response->id }})"
                                                wire:confirm="{{ __('Move this response to trash?') }}"
                                                aria-label="{{ __('Delete response') }}"
                                            />
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $responses->links() }}
        </div>
    @endif
</section>
