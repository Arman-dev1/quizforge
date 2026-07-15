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

        return [
            'responses' => $responses,
            'emails' => $emails,
            'canManage' => Auth::user()->can('update', $this->quiz),
            'scored' => (bool) ($this->quiz->settings['scored'] ?? false),
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('quizzes.show', $quiz) }}" wire:navigate class="flex items-center gap-1 text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                <flux:icon.arrow-left class="size-3.5" />
                {{ $quiz->name }}
            </a>
            <flux:heading size="xl" class="mt-1">{{ __('Responses') }}</flux:heading>
        </div>

        <div class="flex items-center gap-2">
            <flux:select wire:model.live="status" class="w-40" aria-label="{{ __('Filter by status') }}">
                <option value="all">{{ __('All responses') }}</option>
                <option value="completed">{{ __('Completed') }}</option>
                <option value="partial">{{ __('Partial') }}</option>
                <option value="trashed">{{ __('Trashed') }}</option>
            </flux:select>

            <flux:button href="{{ route('quizzes.responses.export', $quiz) }}" variant="filled" icon="arrow-down-tray">
                {{ __('Export CSV') }}
            </flux:button>
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
        <div class="mt-6 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full min-w-160 text-sm">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">{{ __('Respondent') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        @if ($scored)
                            <th class="px-4 py-3">{{ __('Score') }}</th>
                        @endif
                        <th class="px-4 py-3">{{ __('Started') }}</th>
                        <th class="px-4 py-3">{{ __('Duration') }}</th>
                        <th class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($responses as $response)
                        <tr wire:key="response-{{ $response->id }}" class="bg-white dark:bg-zinc-900">
                            <td class="px-4 py-3">
                                <a href="{{ route('quizzes.responses.show', [$quiz, $response->id]) }}" wire:navigate class="font-medium text-zinc-800 hover:underline dark:text-white">
                                    {{ $emails[$response->id] ?? __('Response #:id', ['id' => $response->id]) }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-300' => $response->isCompleted(),
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300' => ! $response->isCompleted(),
                                ])>
                                    {{ $response->isCompleted() ? __('Completed') : __('Partial') }}
                                </span>
                            </td>
                            @if ($scored)
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                    @if ($response->score !== null)
                                        {{ $response->score }}/{{ $response->max_score }}
                                        <span class="text-xs text-zinc-400">({{ $response->percentage }}%)</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $response->started_at->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                {{ $response->completed_at ? $response->started_at->shortAbsoluteDiffForHumans($response->completed_at) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
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

        <div class="mt-4">
            {{ $responses->links() }}
        </div>
    @endif
</section>
