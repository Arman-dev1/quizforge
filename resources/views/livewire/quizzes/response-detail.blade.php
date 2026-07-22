<?php

use App\Models\Quiz;
use App\Models\QuizResponse;
use App\Services\Responses\AnswerFormatter;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public Quiz $quiz;
    public int $responseId;
    public string $notes = '';

    public function mount(Quiz $quiz, int $response): void
    {
        $this->authorize('view', $quiz);

        $this->quiz = $quiz;
        $this->responseId = $response;

        $this->notes = $this->response()->notes ?? '';
    }

    protected function response(): QuizResponse
    {
        return $this->quiz->responses()->withTrashed()->findOrFail($this->responseId);
    }

    public function updatedNotes(string $value): void
    {
        $this->authorize('update', $this->quiz);

        $this->response()->update(['notes' => mb_substr(trim($value), 0, 5000) ?: null]);

        $this->dispatch('notes-saved');
    }

    public function deleteResponse(): void
    {
        $this->authorize('update', $this->quiz);

        $this->response()->delete();

        $this->redirectRoute('quizzes.responses', $this->quiz, navigate: true);
    }

    public function with(): array
    {
        $response = $this->response()->load('answers', 'version');

        $questions = $response->version->questionsById();
        $answers = $response->answers->keyBy('question_id');
        $formatter = app(AnswerFormatter::class);

        $rows = [];

        foreach ($questions as $questionId => $question) {
            $rows[] = [
                'title' => $question['title'],
                'value' => $formatter->format($question, $answers[$questionId]->value ?? null),
            ];
        }

        return [
            'response' => $response,
            'rows' => $rows,
            'canManage' => Auth::user()->can('update', $this->quiz),
            'scored' => $response->score !== null,
        ];
    }
}; ?>

<section class="mx-auto w-full max-w-3xl">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('quizzes.responses', $quiz) }}" wire:navigate class="flex items-center gap-1.5 text-xs font-semibold text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                <flux:icon.arrow-left class="size-3.5" />
                {{ __('All responses') }}
            </a>

            <div class="mt-1 flex items-center gap-3">
                <flux:heading size="xl" class="tracking-tight">{{ __('Response #:id', ['id' => $response->id]) }}</flux:heading>
                <span @class([
                    'rounded-full border px-2.5 py-0.5 text-xs font-semibold',
                    'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950/60 dark:text-green-300' => $response->isCompleted(),
                    'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300' => ! $response->isCompleted(),
                ])>
                    {{ $response->isCompleted() ? __('Completed') : __('Partial') }}
                </span>
                @if ($response->trashed())
                    <span class="rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/60 dark:text-red-300">
                        {{ __('Trashed') }}
                    </span>
                @endif
            </div>

            <flux:subheading class="mt-1">
                {{ __('Started :started', ['started' => $response->started_at->format('M j, Y H:i')]) }}
                @if ($response->completed_at)
                    &middot; {{ __('took :duration', ['duration' => $response->started_at->shortAbsoluteDiffForHumans($response->completed_at)]) }}
                @endif
            </flux:subheading>
        </div>

        @if ($canManage && ! $response->trashed())
            <x-confirm
                action="deleteResponse"
                :title="__('Move to trash?')"
                :description="__('This response moves to the trash. You can restore it later.')"
                :confirm="__('Move to trash')"
                icon="trash"
            >
                <x-slot:trigger>
                    <flux:button variant="filled" icon="trash">{{ __('Delete') }}</flux:button>
                </x-slot:trigger>
            </x-confirm>
        @endif
    </div>

    @if ($scored)
        <div class="mt-6 flex flex-wrap items-center gap-6 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Score') }}</p>
                <p class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-white">
                    {{ $response->score }}<span class="text-base text-zinc-400">/{{ $response->max_score }}</span>
                    <span class="text-sm font-normal text-zinc-500">({{ $response->percentage }}%)</span>
                </p>
            </div>

            @if ($response->passed !== null)
                <span @class([
                    'rounded-full px-3 py-1 text-xs font-semibold',
                    'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-300' => $response->passed,
                    'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-300' => ! $response->passed,
                ])>
                    {{ $response->passed ? __('Passed') : __('Not passed') }}
                </span>
            @endif

            @if ($response->grade)
                <span class="rounded-full bg-teal-100 px-3 py-1 text-xs font-semibold text-teal-800 dark:bg-teal-950/60 dark:text-teal-300">
                    {{ $response->grade }}
                </span>
            @endif
        </div>
    @endif

    <div class="mt-4 divide-y divide-zinc-100 overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:divide-zinc-800/70 dark:border-zinc-800 dark:bg-zinc-900">
        @foreach ($rows as $row)
            <div class="px-5 py-4">
                <p class="font-mono text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $row['title'] }}</p>
                <p class="mt-1.5 whitespace-pre-line text-sm text-zinc-800 dark:text-zinc-200">
                    {{ $row['value'] !== '' ? $row['value'] : '—' }}
                </p>
            </div>
        @endforeach
    </div>

    @if ($canManage)
        <div class="mt-4 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:textarea
                wire:model.blur="notes"
                label="{{ __('Internal notes') }}"
                rows="3"
                placeholder="{{ __('Only your team can see these…') }}"
            />
            <x-action-message on="notes-saved" class="mt-1 text-xs">{{ __('Saved.') }}</x-action-message>
        </div>
    @endif
</section>
