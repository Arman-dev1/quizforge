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
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('components.layouts.player')] class extends Component {
    // Everything the respondent must not be able to rewrite. Without
    // #[Locked] a crafted Livewire update could point the player at
    // another quiz's version, skip pages, or re-open a closed quiz.
    #[Locked]
    public int $quizId;

    #[Locked]
    public int $versionId;

    #[Locked]
    public int $step = 0;

    #[Locked]
    public bool $completed = false;

    #[Locked]
    public bool $closed = false;

    /** @var array<int|string, mixed> answers keyed by question id */
    public array $answers = [];

    /** Respondent-facing result data set at completion. */
    #[Locked]
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
        $this->cachedQuiz = $quiz;
        $this->cachedVersion = $version;

        // Closed, or the monthly response quota is spent: behave exactly
        // like a closed quiz — billing details never leak to respondents.
        if (! $this->acceptingResponses()) {
            $this->closed = true;

            return;
        }

        \App\Models\QuizView::record($quiz);

        $this->resumeExistingResponse();
        $this->initializeAnswerDefaults();
    }

    protected function quiz(): Quiz
    {
        return $this->cachedQuiz ??= Quiz::withoutGlobalScope('workspace')->findOrFail($this->quizId);
    }

    /**
     * Always resolved through the quiz, so a tampered version id can never
     * surface another quiz's (or an unpublished quiz's) content.
     */
    protected function version(): QuizVersion
    {
        return $this->cachedVersion ??= $this->quiz()->versions()->findOrFail($this->versionId);
    }

    /**
     * The gates that decide whether this quiz is still taking answers.
     * Checked on every submission, not just at mount.
     */
    protected function acceptingResponses(): bool
    {
        $quiz = $this->quiz();

        return ! in_array($quiz->status, [QuizStatus::Draft, QuizStatus::Archived, QuizStatus::Closed], true)
            && app(\App\Services\Billing\UsageLimits::class)->canAcceptResponse($quiz->workspace);
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

    protected function throttleKey(): string
    {
        return 'player:'.$this->quizId.':'.request()->ip();
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
     * Seed the answer shapes that the inputs need before they are touched.
     *
     * Ranking starts in the shown order so an untouched list is still a
     * valid answer.
     *
     * Multiple choice matters most: Livewire only gathers checkboxes into an
     * array when the bound property already IS an array. Left as null, every
     * box behaves as an independent boolean and only one selection survives.
     */
    protected function initializeAnswerDefaults(): void
    {
        $page = $this->pages()[$this->step] ?? null;

        foreach ($page['questions'] ?? [] as $question) {
            if (isset($this->answers[$question['id']])) {
                continue;
            }

            $this->answers[$question['id']] = match ($question['type']) {
                QuestionType::Ranking->value => array_column($question['options'], 'id'),
                QuestionType::MultipleChoice->value => [],
                default => null,
            };

            // Leave untouched types genuinely unset rather than null, so
            // "not answered" logic conditions still read correctly.
            if ($this->answers[$question['id']] === null) {
                unset($this->answers[$question['id']]);
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

        // Re-assert the gates: a quiz can be closed, archived, or hit its
        // monthly quota while a respondent is part-way through.
        if (! $this->acceptingResponses()) {
            $this->closed = true;

            return;
        }

        // The route throttle only covers the initial page load; submissions
        // are POSTs to /livewire/update, so they need their own limit.
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($this->throttleKey(), 40)) {
            throw ValidationException::withMessages([
                'answers' => __('Too many submissions. Please wait a moment and try again.'),
            ]);
        }

        \Illuminate\Support\Facades\RateLimiter::hit($this->throttleKey(), 60);

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
        $this->initializeAnswerDefaults();
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

        // Score against what was actually PERSISTED, never against the
        // public $answers property — that array is client-writable, so
        // scoring it would let a respondent grade their own paper.
        $storedAnswers = $response->answers()->pluck('value', 'question_id')->all();

        $visiblePages = array_map(fn (array $page) => [
            'questions' => $logicEngine->visibleQuestions($page['questions'] ?? [], $storedAnswers),
        ], $this->pages());

        if ($quiz->settings['scored'] ?? false) {
            $scoreResult = app(ScoringEngine::class)->score($visiblePages, $storedAnswers);
        }

        // Category tally drives category-matched result screens; it is cheap
        // and only consulted when the quiz is in that mode.
        $categoryTally = app(\App\Services\Scoring\CategoryScorer::class)->tally($visiblePages, $storedAnswers);

        $resolved = app(ResultResolver::class)->resolve($quiz->settings ?? [], $scoreResult, $categoryTally);

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

        \Illuminate\Support\Facades\Notification::send(
            $quiz->workspace->members()->get(),
            new \App\Notifications\NewResponse($response),
        );

        // Push the completed response to any connected email-marketing tools —
        // only while the workspace's plan still includes integrations.
        if (app(\App\Services\Billing\UsageLimits::class)->feature($quiz->workspace, 'integrations')) {
            foreach ($quiz->integrations()->where('status', 'connected')->get() as $integration) {
                \App\Jobs\SyncQuizResponse::dispatch($integration->id, $response->id);
            }
        }

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
            'title' => $resolved->title,
            'description' => $resolved->description,
        ];
    }

    protected function storeAnswers(QuizResponse $response, array $questions): void
    {
        $hadEmailAnswer = $response->answers()->where('question_type', 'email')->exists();

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

        if (! $hadEmailAnswer) {
            $email = $response->answers()->where('question_type', 'email')->first()?->value;

            if ($email) {
                \Illuminate\Support\Facades\Notification::send(
                    $this->quiz()->workspace->members()->get(),
                    new \App\Notifications\NewLead($response, (string) $email),
                );
            }
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

        $quiz = $this->quiz();
        $design = \App\Services\Design\QuizDesign::forQuiz($quiz);
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $proActive = app(\App\Services\Billing\UsageLimits::class)->feature($quiz->workspace, 'custom_code');
        $canRemoveBranding = app(\App\Services\Billing\UsageLimits::class)->feature($quiz->workspace, 'remove_branding');

        return [
            'quiz' => $quiz,
            'page' => $page,
            'total' => count($pages),
            'progress' => count($pages) > 0 ? (int) round((($this->step + 1) / count($pages)) * 100) : 0,
            'design' => $design,
            'designStyle' => \App\Services\Design\QuizDesign::styleAttribute($design),
            'designBackground' => \App\Services\Design\QuizDesign::backgroundCss($design, $design['background_image'] ? $disk->url($design['background_image']) : null),
            'designLogo' => $design['logo'] ? $disk->url($design['logo']) : null,
            'designCover' => $design['cover'] ? $disk->url($design['cover']) : null,
            'designCustomCss' => $proActive ? str_ireplace('</style', '<\/style', trim((string) ($design['custom_css'] ?? ''))) : '',
            // Shown unless the plan allows removal AND the author turned it
            // off — so a downgrade puts the branding straight back.
            'showBranding' => ! (($design['hide_branding'] ?? false) && $canRemoveBranding),
            'designCustomJs' => $proActive ? str_ireplace('</script', '<\/script', trim((string) ($design['custom_js'] ?? ''))) : '',
        ];
    }
}; ?>

<div
    class="flex min-h-svh flex-col"
    id="qf-player"
    data-input-style="{{ $design['input_style'] ?? 'outline' }}"
    style="{{ $designStyle }};background:{{ $designBackground }};color:var(--qf-text);font-family:var(--qf-font);font-size:var(--qf-font-size)"
>
    <style>
        #qf-player .qf-primary-btn { background-color: var(--qf-primary) !important; border-color: var(--qf-primary) !important; color: var(--qf-primary-ink) !important; border-radius: var(--qf-btn-radius) !important; }
        #qf-player .qf-card { border-radius: calc(var(--qf-radius) + 6px) !important; }
    </style>
    @if ($designCustomCss !== '')
        <style>{!! $designCustomCss !!}</style>
    @endif

    @unless ($closed || $completed)
        <div
            class="h-1.5 w-full bg-black/10 dark:bg-white/10"
            role="progressbar"
            aria-valuenow="{{ $progress }}"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-label="{{ __('Quiz progress') }}"
            @if ($design['progress_style'] === 'hidden') hidden @endif
        >
            <div class="h-full transition-all duration-300" style="width: {{ $progress }}%;background:var(--qf-primary)"></div>
        </div>
    @endunless

    <main class="mx-auto flex w-full max-w-2xl flex-1 flex-col px-4 py-10 sm:px-6">
        @if ($closed)
            <div class="qf-card m-auto rounded-2xl border border-zinc-200 bg-white p-10 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <flux:icon.lock-closed class="mx-auto size-10 text-zinc-400" />
                <flux:heading size="lg" class="mt-4">{{ $quiz->name }}</flux:heading>
                <flux:subheading class="mt-1">{{ __('This quiz is no longer accepting responses.') }}</flux:subheading>
            </div>
        @elseif ($completed)
            <div class="qf-card m-auto w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-10 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <span class="mx-auto flex size-14 items-center justify-center rounded-full" style="background:var(--qf-primary)">
                    <flux:icon.check class="size-7 text-white" />
                </span>

                <flux:heading size="lg" class="mt-5">
                    {{ ($outcome['title'] ?? null) ?: __('Thank you!') }}
                </flux:heading>

                @if ($outcome['description'] ?? null)
                    {{-- Sanitized on save by HtmlSanitizer; never render
                         author HTML that has not been through it. --}}
                    <div class="qf-prose mt-3 text-left text-sm text-zinc-600 dark:text-zinc-300">
                        {!! $outcome['description'] !!}
                    </div>
                @else
                    <flux:subheading class="mt-1">
                        {{ ($outcome['message'] ?? null) ?: __('Your response has been recorded.') }}
                    </flux:subheading>
                @endif

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
                                <span class="rounded-full bg-teal-100 px-3 py-1 text-xs font-semibold text-teal-800 dark:bg-teal-950/60 dark:text-teal-300">
                                    {{ $outcome['grade'] }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($outcome['redirect'] ?? null)
                    <flux:button href="{{ $outcome['redirect'] }}" variant="primary" class="qf-primary-btn mt-6">
                        {{ __('Continue') }}
                    </flux:button>
                @endif
            </div>
        @elseif ($page)
            @if ($designCover)
                <img src="{{ $designCover }}" alt="" class="qf-card mb-6 h-40 w-full border border-black/5 object-cover shadow-sm" />
            @endif

            @if ($step === 0)
                <div class="mb-6 text-center">
                    @if ($designLogo)
                        <img src="{{ $designLogo }}" alt="" class="mx-auto mb-4 h-12 w-auto" />
                    @endif
                    <flux:heading size="xl">{{ $quiz->name }}</flux:heading>
                    @if ($quiz->description)
                        <flux:subheading class="mt-1">{{ $quiz->description }}</flux:subheading>
                    @endif
                </div>
            @endif

            <div class="qf-card rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 dark:border-zinc-800 dark:bg-zinc-900">
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

                <flux:button wire:click="next" variant="primary" class="qf-primary-btn" icon-trailing="{{ $step < $total - 1 ? 'arrow-right' : 'check' }}">
                    {{ $step < $total - 1 ? __('Next') : __('Submit') }}
                </flux:button>
            </div>
        @endif

        @if ($showBranding)
            <p class="mt-8 text-center text-xs" style="color:var(--qf-muted)">
                {{ __('Powered by') }} <span class="font-semibold">QuizForge</span>
            </p>
        @endif
    </main>

    @if ($designCustomJs !== '')
        {{-- Pro custom JS: runs once on the public player's initial load. --}}
        <script>{!! $designCustomJs !!}</script>
    @endif
</div>
