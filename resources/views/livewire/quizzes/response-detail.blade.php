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

<section class="mx-auto flex w-full max-w-3xl flex-col gap-5">
    <x-page-header
        :title="__('Response #:id', ['id' => $response->id])"
        :back="route('quizzes.responses', $quiz)"
        :back-label="__('All responses')"
    >
        <x-slot:meta>
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

            @if ($response->trashed())
                <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700 dark:bg-red-950/60 dark:text-red-400">
                    {{ __('In trash') }}
                </span>
            @endif

            <span>{{ $quiz->name }}</span>
            <span class="text-zinc-300 dark:text-zinc-700">&middot;</span>
            <span>{{ $response->started_at->format('M j, Y H:i') }}</span>
            @if ($response->completed_at)
                <span class="text-zinc-300 dark:text-zinc-700">&middot;</span>
                <span>{{ __('took :duration', ['duration' => $response->started_at->shortAbsoluteDiffForHumans($response->completed_at)]) }}</span>
            @endif
        </x-slot:meta>

        @if ($canManage && ! $response->trashed())
            <x-confirm
                action="deleteResponse"
                :title="__('Move to trash?')"
                :description="__('This response moves to the trash. You can restore it later from the Trash filter.')"
                :confirm="__('Move to trash')"
                icon="trash"
            >
                <x-slot:trigger>
                    <flux:button variant="filled" icon="trash">{{ __('Move to trash') }}</flux:button>
                </x-slot:trigger>
            </x-confirm>
        @endif
    </x-page-header>

    @if ($scored)
        <div class="qf-surface flex flex-wrap items-center gap-x-8 gap-y-4 p-5">
            <div>
                <p class="qf-eyebrow">{{ __('Score') }}</p>
                <p class="qf-num mt-1.5 text-3xl font-extrabold text-zinc-900 dark:text-white">
                    {{ $response->score }}<span class="text-lg font-medium text-zinc-400">/{{ $response->max_score }}</span>
                </p>
            </div>

            <div>
                <p class="qf-eyebrow">{{ __('Percentage') }}</p>
                <p class="qf-num mt-1.5 text-3xl font-extrabold text-zinc-900 dark:text-white">{{ $response->percentage }}%</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($response->passed !== null)
                    <span @class([
                        'rounded-full px-3 py-1 text-xs font-bold',
                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' => $response->passed,
                        'bg-red-50 text-red-700 dark:bg-red-950/60 dark:text-red-400' => ! $response->passed,
                    ])>
                        {{ $response->passed ? __('Passed') : __('Not passed') }}
                    </span>
                @endif

                @if ($response->grade)
                    <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-bold text-teal-700 dark:bg-teal-950/60 dark:text-teal-400">
                        {{ $response->grade }}
                    </span>
                @endif
            </div>

            @if ($response->max_score > 0)
                <div class="w-full">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div @class([
                            'h-full rounded-full',
                            'bg-emerald-600' => $response->passed,
                            'bg-red-500' => $response->passed === false,
                            'bg-teal-600' => $response->passed === null,
                        ]) style="width: {{ (int) min(100, max(0, $response->percentage ?? 0)) }}%"></div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <x-panel :title="__('Answers')" icon="chat-bubble-left-right" flush>
        <dl class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($rows as $row)
                <div class="px-5 py-4">
                    <dt class="qf-eyebrow">{{ $row['title'] }}</dt>
                    <dd class="mt-1.5 whitespace-pre-line text-sm text-zinc-800 dark:text-zinc-200">
                        {{ $row['value'] !== '' ? $row['value'] : '—' }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </x-panel>

    @if ($canManage)
        <x-panel :title="__('Internal notes')" icon="pencil-square" :description="__('Only your team can see these.')">
            <flux:textarea
                wire:model.blur="notes"
                rows="3"
                :placeholder="__('Add context for your team…')"
                :aria-label="__('Internal notes')"
            />
            <x-action-message on="notes-saved" class="mt-2 text-xs">{{ __('Saved.') }}</x-action-message>
        </x-panel>
    @endif
</section>
