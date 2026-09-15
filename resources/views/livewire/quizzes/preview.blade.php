<?php

use App\Actions\Quizzes\PublishQuiz;
use App\Enums\QuestionType;
use App\Models\Quiz;
use App\Services\Design\QuizDesign;
use App\Services\Logic\LogicEngine;
use App\Services\Player\AnswerValidator;
use App\Services\Scoring\CategoryScorer;
use App\Services\Scoring\ResultResolver;
use App\Services\Scoring\ScoringEngine;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

/**
 * A full dry run of the quiz for its author.
 *
 * It renders the *draft* content through the same snapshot shape the public
 * player consumes, and runs the same logic, validation and scoring engines —
 * so what you try here is what respondents get. The one difference is that
 * nothing is persisted: no QuizResponse, no answers, no view counts, no
 * notifications, no integration syncs.
 */
new #[Layout('components.layouts.player')] class extends Component {
    #[Locked]
    public int $quizId;

    public int $step = 0;

    public bool $completed = false;

    /** @var array<int|string, mixed> answers keyed by question id */
    public array $answers = [];

    public array $outcome = [];

    protected ?Quiz $cachedQuiz = null;

    protected ?array $cachedPages = null;

    public function mount(Quiz $quiz): void
    {
        $this->authorize('view', $quiz);

        $this->quizId = $quiz->id;
        $this->cachedQuiz = $quiz;

        $this->initializeAnswerDefaults();
    }

    protected function quiz(): Quiz
    {
        return $this->cachedQuiz ??= Quiz::findOrFail($this->quizId);
    }

    /**
     * Draft content in snapshot shape — identical to what publishing would
     * write, so the preview can never drift from the real player.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function pages(): array
    {
        return $this->cachedPages ??= app(PublishQuiz::class)->buildContent($this->quiz())['pages'];
    }

    /**
     * Mirrors the player exactly — see the note there. Multiple choice must
     * start as an array or Livewire binds each checkbox as a boolean.
     */
    protected function initializeAnswerDefaults(): void
    {
        $page = $this->pages()[$this->step] ?? null;

        foreach ($page['questions'] ?? [] as $question) {
            if (isset($this->answers[$question['id']])) {
                continue;
            }

            $default = match ($question['type']) {
                QuestionType::Ranking->value => array_column($question['options'], 'id'),
                QuestionType::MultipleChoice->value => [],
                default => null,
            };

            if ($default !== null) {
                $this->answers[$question['id']] = $default;
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
        if ($this->completed) {
            return;
        }

        $pages = $this->pages();
        $page = $pages[$this->step] ?? null;

        if (! $page) {
            return;
        }

        // Same validator the public player runs, so required fields and
        // per-type rules behave exactly as they will in production.
        $visible = app(LogicEngine::class)->visibleQuestions($page['questions'], $this->answers);

        $validator->validate($visible, $this->answers);

        if ($this->step >= count($pages) - 1) {
            $this->finish();

            return;
        }

        $this->step++;
        $this->initializeAnswerDefaults();
    }

    public function previous(): void
    {
        if (! $this->completed && $this->step > 0) {
            $this->step--;
        }
    }

    /** Score and resolve the outcome in memory. Nothing is written. */
    protected function finish(): void
    {
        $quiz = $this->quiz();
        $logicEngine = app(LogicEngine::class);
        $scoreResult = null;

        $visiblePages = array_map(fn (array $page) => [
            'questions' => $logicEngine->visibleQuestions($page['questions'] ?? [], $this->answers),
        ], $this->pages());

        if ($quiz->settings['scored'] ?? false) {
            $scoreResult = app(ScoringEngine::class)->score($visiblePages, $this->answers);
        }

        $categoryTally = app(CategoryScorer::class)->tally($visiblePages, $this->answers);

        $resolved = app(ResultResolver::class)->resolve($quiz->settings ?? [], $scoreResult, $categoryTally);

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
            'category' => $resolved->category,
        ];

        $this->completed = true;
    }

    /** Start over without leaving the page. */
    public function restart(): void
    {
        $this->reset(['step', 'answers', 'outcome', 'completed']);
        $this->initializeAnswerDefaults();
    }

    public function with(): array
    {
        $pages = $this->pages();
        $this->step = max(0, min($this->step, max(count($pages) - 1, 0)));

        $page = $this->completed ? null : ($pages[$this->step] ?? null);

        if ($page !== null) {
            $page['questions'] = app(LogicEngine::class)->visibleQuestions($page['questions'] ?? [], $this->answers);
        }

        $quiz = $this->quiz();
        $design = QuizDesign::forQuiz($quiz);
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        return [
            'quiz' => $quiz,
            'page' => $page,
            'total' => count($pages),
            'progress' => count($pages) > 0 ? (int) round((($this->step + 1) / count($pages)) * 100) : 0,
            'design' => $design,
            'designStyle' => QuizDesign::styleAttribute($design),
            'designBackground' => QuizDesign::backgroundCss($design, $design['background_image'] ? $disk->url($design['background_image']) : null),
            'designLogo' => $design['logo'] ? $disk->url($design['logo']) : null,
            'designCover' => $design['cover'] ? $disk->url($design['cover']) : null,
            // Matches the player, so the preview shows what respondents get.
            'showBranding' => ! (
                ($design['hide_branding'] ?? false)
                && app(\App\Services\Billing\UsageLimits::class)->feature($quiz->workspace, 'remove_branding')
            ),
        ];
    }
}; ?>

<div class="flex min-h-svh flex-col">
    {{-- Preview banner. Deliberately loud: someone landing here must never
         mistake this for the live quiz. --}}
    <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 bg-zinc-900 px-4 py-2.5 text-center text-xs font-medium text-white">
        <span class="flex items-center gap-1.5">
            <flux:icon.eye class="size-3.5" />
            {{ __('Preview — you can answer and submit, but nothing is saved.') }}
        </span>

        <div class="flex items-center gap-3">
            @if ($completed || $step > 0)
                <button type="button" wire:click="restart" class="font-semibold underline underline-offset-2 hover:text-teal-300">
                    {{ __('Start over') }}
                </button>
            @endif

            @can('update', $quiz)
                <a href="{{ route('quizzes.builder', $quiz) }}" wire:navigate class="font-semibold underline underline-offset-2 hover:text-teal-300">
                    {{ __('Back to builder') }}
                </a>
            @endcan
        </div>
    </div>

    <div
        id="qf-player"
        class="flex flex-1 flex-col"
        data-input-style="{{ $design['input_style'] ?? 'outline' }}"
        style="{{ $designStyle }};background:{{ $designBackground }};color:var(--qf-text);font-family:var(--qf-font);font-size:var(--qf-font-size)"
    >
        <style>
            #qf-player .qf-primary-btn { background-color: var(--qf-primary) !important; border-color: var(--qf-primary) !important; color: var(--qf-primary-ink) !important; border-radius: var(--qf-btn-radius) !important; }
            #qf-player .qf-card { border-radius: calc(var(--qf-radius) + 6px) !important; }
        </style>

        @unless ($completed)
            <div
                class="h-1.5 w-full bg-black/10"
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
            @if ($completed)
                <div class="qf-card m-auto w-full max-w-md rounded-2xl p-10 text-center shadow-sm">
                    <span class="mx-auto flex size-14 items-center justify-center rounded-full" style="background:var(--qf-primary)">
                        <flux:icon.check class="size-7 text-white" />
                    </span>

                    <flux:heading size="lg" class="mt-5">
                        {{ ($outcome['title'] ?? null) ?: __('Thank you!') }}
                    </flux:heading>

                    @if ($outcome['category'] ?? null)
                        <p class="qf-inset qf-muted mt-2 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold">
                            <flux:icon.tag class="size-3.5" />
                            {{ __('Matched category: :name', ['name' => $outcome['category']]) }}
                        </p>
                    @endif

                    @if ($outcome['description'] ?? null)
                        <div class="qf-prose mt-3 text-left text-sm">
                            {!! $outcome['description'] !!}
                        </div>
                    @else
                        <flux:subheading class="mt-1">
                            {{ ($outcome['message'] ?? null) ?: __('Your response has been recorded.') }}
                        </flux:subheading>
                    @endif

                    @if ($outcome['show_score'] ?? false)
                        <div class="qf-inset mt-6 p-5">
                            <p class="qf-num text-4xl font-bold">
                                {{ $outcome['points'] }}<span class="text-xl font-medium text-zinc-400">/{{ $outcome['max'] }}</span>
                            </p>
                            <p class="qf-muted mt-1 text-sm">
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
                                        'bg-emerald-100 text-emerald-800' => $outcome['passed'],
                                        'bg-red-100 text-red-800' => ! $outcome['passed'],
                                    ])>
                                        {{ $outcome['passed'] ? __('Passed') : __('Not passed') }}
                                    </span>
                                @endif

                                @if ($outcome['grade'] ?? null)
                                    <span class="rounded-full bg-teal-100 px-3 py-1 text-xs font-semibold text-teal-800">
                                        {{ $outcome['grade'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($outcome['redirect'] ?? null)
                        <p class="qf-inset qf-muted mt-6 px-3 py-2 text-xs">
                            {{ __('Respondents would get a button here to :url', ['url' => $outcome['redirect']]) }}
                        </p>
                    @endif

                    <flux:button wire:click="restart" variant="filled" class="mt-6">{{ __('Run through again') }}</flux:button>
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

                <div class="qf-card rounded-2xl p-6 shadow-sm sm:p-8">
                    @if (($page['title'] ?? '') !== '' || ($page['description'] ?? '') !== '')
                        <div class="mb-8">
                            @if (($page['title'] ?? '') !== '')
                                <flux:heading size="lg">{{ $page['title'] }}</flux:heading>
                            @endif
                            @if (($page['description'] ?? '') !== '')
                                <flux:subheading class="mt-1">{{ $page['description'] }}</flux:subheading>
                            @endif
                        </div>
                    @endif

                    @if ($page['questions'] === [])
                        <flux:text class="text-zinc-500">{{ __('This page has no visible questions yet.') }}</flux:text>
                    @else
                        <div class="flex flex-col gap-8">
                            @foreach ($page['questions'] as $question)
                                <div wire:key="preview-question-{{ $question['id'] }}">
                                    @include('partials.question-input', ['question' => $question])
                                    @error('answers.'.$question['id'])
                                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-6 flex items-center justify-between gap-3">
                    <div>
                        @if ($step > 0)
                            <flux:button wire:click="previous" variant="filled" icon="arrow-left">{{ __('Previous') }}</flux:button>
                        @endif
                    </div>

                    <flux:text class="text-xs text-zinc-500">
                        {{ __('Page :current of :total', ['current' => $step + 1, 'total' => max($total, 1)]) }}
                    </flux:text>

                    <flux:button wire:click="next" variant="primary" class="qf-primary-btn" icon-trailing="arrow-right">
                        {{ $step >= $total - 1 ? __('Submit') : __('Next') }}
                    </flux:button>
                </div>
            @else
                <div class="m-auto text-center">
                    <flux:icon.puzzle-piece class="mx-auto size-10 text-zinc-400" />
                    <flux:heading size="lg" class="mt-4">{{ __('Nothing to preview yet') }}</flux:heading>
                    <flux:subheading>{{ __('Add questions in the builder and they will appear here.') }}</flux:subheading>

                    @can('update', $quiz)
                        <flux:button :href="route('quizzes.builder', $quiz)" wire:navigate variant="primary" icon="squares-plus" class="mt-5">
                            {{ __('Open the builder') }}
                        </flux:button>
                    @endcan
                </div>
            @endif

            @if ($showBranding)
                <p class="qf-muted mt-8 text-center text-xs">
                    {{ __('Powered by') }} <span class="font-semibold">QuizForge</span>
                </p>
            @endif
        </main>
    </div>
</div>
