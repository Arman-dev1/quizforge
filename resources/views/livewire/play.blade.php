<?php

use App\Enums\QuestionType;
use App\Enums\QuizStatus;
use App\Models\Quiz;
use App\Models\QuizResponse;
use App\Models\QuizVersion;
use App\Services\Logic\LogicEngine;
use App\Services\Player\AnswerValidator;
use App\Services\Scoring\ResultResolver;
use App\Services\Scoring\ScoringEngine;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.player')] class extends Component {
    public int $quizId;
    public int $versionId;
    public int $step = 0;
    public bool $completed = false;
    public bool $closed = false;

    /** @var array<int|string, mixed> answers keyed by question id */
    public array $answers = [];

    /** Respondent-facing result data set at completion. */
    public array $outcome = [];

    protected ?Quiz $cachedQuiz = null;
    protected ?QuizVersion $cachedVersion = null;

    public function mount(string $slug): void
    {
        $quiz = Quiz::withoutGlobalScope('workspace')->where('slug', $slug)->firstOrFail();

        abort_if(in_array($quiz->status, [QuizStatus::Draft, QuizStatus::Archived], true), 404);

        $version = $quiz->latestVersion();

        abort_unless($version, 404);

        $this->quizId = $quiz->id;
        $this->versionId = $version->id;

        if ($quiz->status === QuizStatus::Closed) {
            $this->closed = true;

            return;
        }

        \App\Models\QuizView::record($quiz);

        $this->resumeExistingResponse();
        $this->initializeRankingDefaults();
    }

    protected function quiz(): Quiz
    {
        return $this->cachedQuiz ??= Quiz::withoutGlobalScope('workspace')->findOrFail($this->quizId);
    }

    protected function version(): QuizVersion
    {
        return $this->cachedVersion ??= QuizVersion::findOrFail($this->versionId);
    }

    /** @return array<int, array<string, mixed>> */
    protected function pages(): array
    {
        return $this->version()->pages();
    }

    protected function sessionKey(): string
    {
        return 'player.token.'.$this->quizId;
    }

    protected function resumeExistingResponse(): void
    {
        $response = $this->existingResponse();

        if ($response) {
            $this->step = min($response->current_page, max(count($this->pages()) - 1, 0));
            $this->answers = $response->answers()->pluck('value', 'question_id')->all();
        }
    }

    protected function existingResponse(): ?QuizResponse
    {
        $token = session($this->sessionKey());

        if (! $token) {
            return null;
        }

        return QuizResponse::where('respondent_token', $token)
            ->where('quiz_version_id', $this->versionId)
            ->where('status', QuizResponse::STATUS_IN_PROGRESS)
            ->first();
    }

    protected function ensureResponse(): QuizResponse
    {
        if ($response = $this->existingResponse()) {
            return $response;
        }

        $token = Str::random(40);

        session()->put($this->sessionKey(), $token);

        return QuizResponse::create([
            'workspace_id' => $this->quiz()->workspace_id,
            'quiz_id' => $this->quizId,
            'quiz_version_id' => $this->versionId,
            'status' => QuizResponse::STATUS_IN_PROGRESS,
            'respondent_token' => $token,
            'current_page' => $this->step,
            'started_at' => now(),
            'user_agent' => Str::limit((string) request()->userAgent(), 500, ''),
        ]);
    }

    /**
     * Ranking questions default to the shown order so an untouched
     * list is still a valid answer.
     */
    protected function initializeRankingDefaults(): void
    {
        $page = $this->pages()[$this->step] ?? null;

        foreach ($page['questions'] ?? [] as $question) {
            if ($question['type'] === QuestionType::Ranking->value && ! isset($this->answers[$question['id']])) {
                $this->answers[$question['id']] = array_column($question['options'], 'id');
            }
        }
    }

    public function sortRanking(int $optionId, ?string $target, int $position): void
    {
        $questionId = (int) $target;
        $current = $this->answers[$questionId] ?? [];

        $remaining = array_values(array_filter($current, fn ($id) => (int) $id !== $optionId));
        array_splice($remaining, min($position, count($remaining)), 0, [$optionId]);

        $this->answers[$questionId] = $remaining;
    }

    public function next(AnswerValidator $validator): void
    {
        if ($this->closed || $this->completed) {
            return;
        }

        $pages = $this->pages();
        $page = $pages[$this->step] ?? null;

        if (! $page) {
            return;
        }

        $visible = app(LogicEngine::class)->visibleQuestions($page['questions'], $this->answers);

        $validator->validate($visible, $this->answers);

        $response = $this->ensureResponse();

        $this->storeAnswers($response, $visible);

        if ($this->step >= count($pages) - 1) {
            $this->finalizeResponse($response);

            session()->forget($this->sessionKey());

            $this->completed = true;

            return;
        }

        $this->step++;
        $response->update(['current_page' => $this->step]);
        $this->initializeRankingDefaults();
    }

    public function previous(): void
    {
        if (! $this->closed && ! $this->completed && $this->step > 0) {
            $this->step--;
        }
    }

    /**
     * Score (if enabled), resolve the configured outcome, and stamp the
     * result onto the response so later phases never recompute it.
     */
    protected function finalizeResponse(QuizResponse $response): void
    {
        $quiz = $this->quiz();
        $logicEngine = app(LogicEngine::class);
        $scoreResult = null;

        if ($quiz->settings['scored'] ?? false) {
            $visiblePages = array_map(fn (array $page) => [
                'questions' => $logicEngine->visibleQuestions($page['questions'] ?? [], $this->answers),
            ], $this->pages());

            $scoreResult = app(ScoringEngine::class)->score($visiblePages, $this->answers);
        }

        $resolved = app(ResultResolver::class)->resolve($quiz->settings ?? [], $scoreResult);

        $response->update([
            'status' => QuizResponse::STATUS_COMPLETED,
            'completed_at' => now(),
            'current_page' => $this->step,
            'score' => $scoreResult?->points,
            'max_score' => $scoreResult?->maxPoints,
            'percentage' => $scoreResult?->percentage,
            'passed' => $resolved->passed,
            'grade' => $resolved->grade,
        ]);

        $this->outcome = [
            'show_score' => $resolved->showScore && $scoreResult !== null && $scoreResult->maxPoints > 0,
            'points' => $scoreResult?->points,
            'max' => $scoreResult?->maxPoints,
            'percentage' => $scoreResult?->percentage,
            'correct' => $scoreResult?->correctCount,
            'scored_questions' => $scoreResult?->scoredCount,
            'passed' => $resolved->passed,
            'grade' => $resolved->grade,
            'message' => $resolved->message,
            'redirect' => $resolved->redirectUrl,
        ];
    }

    protected function storeAnswers(QuizResponse $response, array $questions): void
    {
        foreach ($questions as $question) {
            $value = $this->answers[$question['id']] ?? null;

            if ($value === null || $value === '' || $value === []) {
                // Cleared on back-navigation: remove any stale stored answer.
                $response->answers()->where('question_id', $question['id'])->delete();

                continue;
            }

            $response->answers()->updateOrCreate(
                ['question_id' => $question['id']],
                ['question_type' => $question['type'], 'value' => $value],
            );
        }
    }

    public function with(): array
    {
        $pages = $this->pages();
        $this->step = max(0, min($this->step, max(count($pages) - 1, 0)));

        $page = $this->closed || $this->completed ? null : ($pages[$this->step] ?? null);

        if ($page !== null) {
            $page['questions'] = app(LogicEngine::class)->visibleQuestions($page['questions'] ?? [], $this->answers);
        }

        return [
            'quiz' => $this->quiz(),
            'page' => $page,
            'total' => count($pages),
            'progress' => count($pages) > 0 ? (int) round((($this->step + 1) / count($pages)) * 100) : 0,
        ];
    }
}; ?>

<div class="flex min-h-svh flex-col">
    @unless ($closed || $completed)
        <div class="h-1.5 w-full bg-zinc-200 dark:bg-zinc-800">
            <div class="h-full bg-gradient-to-r from-orange-500 to-red-600 transition-all duration-300" style="width: {{ $progress }}%"></div>
        </div>
    @endunless

    <main class="mx-auto flex w-full max-w-2xl flex-1 flex-col px-4 py-10 sm:px-6">
        @if ($closed)
            <div class="m-auto rounded-2xl border border-zinc-200 bg-white p-10 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <flux:icon.lock-closed class="mx-auto size-10 text-zinc-400" />
                <flux:heading size="lg" class="mt-4">{{ $quiz->name }}</flux:heading>
                <flux:subheading class="mt-1">{{ __('This quiz is no longer accepting responses.') }}</flux:subheading>
            </div>
        @elseif ($completed)
            <div class="m-auto w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-10 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 to-red-600">
                    <flux:icon.check class="size-7 text-white" />
                </span>

                <flux:heading size="lg" class="mt-5">{{ __('Thank you!') }}</flux:heading>
                <flux:subheading class="mt-1">
                    {{ ($outcome['message'] ?? null) ?: __('Your response has been recorded.') }}
                </flux:subheading>

                @if ($outcome['show_score'] ?? false)
                    <div class="mt-6 rounded-xl bg-zinc-50 p-5 dark:bg-zinc-800/60">
                        <p class="text-4xl font-bold tracking-tight text-zinc-900 dark:text-white">
                            {{ $outcome['points'] }}<span class="text-xl font-medium text-zinc-400">/{{ $outcome['max'] }}</span>
                        </p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __(':percentage% — :correct of :total correct', [
                                'percentage' => $outcome['percentage'],
                                'correct' => $outcome['correct'],
                                'total' => $outcome['scored_questions'],
                            ]) }}
                        </p>

                        <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                            @if (($outcome['passed'] ?? null) !== null)
                                <span @class([
                                    'rounded-full px-3 py-1 text-xs font-semibold',
                                    'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-300' => $outcome['passed'],
                                    'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-300' => ! $outcome['passed'],
                                ])>
                                    {{ $outcome['passed'] ? __('Passed') : __('Not passed') }}
                                </span>
                            @endif

                            @if ($outcome['grade'] ?? null)
                                <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-800 dark:bg-orange-950/60 dark:text-orange-300">
                                    {{ $outcome['grade'] }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($outcome['redirect'] ?? null)
                    <flux:button href="{{ $outcome['redirect'] }}" variant="primary" class="mt-6">
                        {{ __('Continue') }}
                    </flux:button>
                @endif
            </div>
        @elseif ($page)
            @if ($step === 0)
                <div class="mb-6 text-center">
                    <flux:heading size="xl">{{ $quiz->name }}</flux:heading>
                    @if ($quiz->description)
                        <flux:subheading class="mt-1">{{ $quiz->description }}</flux:subheading>
                    @endif
                </div>
            @endif

            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 dark:border-zinc-800 dark:bg-zinc-900">
                @if (($page['title'] ?? null) || ($page['description'] ?? null))
                    <div class="mb-8">
                        @if ($page['title'] ?? null)
                            <flux:heading size="lg">{{ $page['title'] }}</flux:heading>
                        @endif
                        @if ($page['description'] ?? null)
                            <flux:subheading class="mt-1">{{ $page['description'] }}</flux:subheading>
                        @endif
                    </div>
                @endif

                <div class="space-y-8">
                    @foreach ($page['questions'] as $question)
                        <div wire:key="play-question-{{ $question['id'] }}">
                            @include('partials.question-input', ['question' => $question])
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <div>
                    @if ($step > 0)
                        <flux:button wire:click="previous" variant="filled" icon="arrow-left">
                            {{ __('Previous') }}
                        </flux:button>
                    @endif
                </div>

                @if ($total > 1)
                    <flux:text class="text-xs text-zinc-500">
                        {{ __('Page :current of :total', ['current' => $step + 1, 'total' => $total]) }}
                    </flux:text>
                @endif

                <flux:button wire:click="next" variant="primary" icon-trailing="{{ $step < $total - 1 ? 'arrow-right' : 'check' }}">
                    {{ $step < $total - 1 ? __('Next') : __('Submit') }}
                </flux:button>
            </div>
        @endif

        <p class="mt-8 text-center text-xs text-zinc-400 dark:text-zinc-500">
            {{ __('Powered by') }} <span class="font-semibold">QuizForge</span>
        </p>
    </main>
</div>
