<?php

use App\Enums\QuestionType;
use App\Models\Quiz;
use App\Services\Scoring\ResultResolver;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

/**
 * The result screens a respondent sees after submitting.
 *
 * Three modes:
 *  - simple:   one thank-you message for everyone
 *  - score:    a screen per score band (0–49%, 50–79%, …)
 *  - category: a screen per category, chosen by which category the
 *              respondent picked most (options carry a category in the builder)
 */
new class extends Component {
    public Quiz $quiz;

    public string $mode = ResultResolver::MODE_SIMPLE;

    public bool $scored = false;

    public bool $showScore = true;

    public string $passPercentage = '';

    public string $thankYouMessage = '';

    public string $redirectUrl = '';

    /** Grade bands as "min%:Label" lines — the short badge on a result. */
    public string $grades = '';

    /** @var array<int, array<string, mixed>> */
    public array $outcomes = [];

    public function mount(Quiz $quiz): void
    {
        $this->authorize('view', $quiz);

        $this->quiz = $quiz;
        $this->load();
    }

    public function boot(): void
    {
        if (isset($this->quiz)) {
            $this->authorize('view', $this->quiz);
        }
    }

    protected function load(): void
    {
        $settings = $this->quiz->settings ?? [];
        $results = $settings['results'] ?? [];

        $this->scored = (bool) ($settings['scored'] ?? false);
        $this->showScore = (bool) ($results['show_score'] ?? true);
        $this->passPercentage = (string) ($results['pass_percentage'] ?? '');
        $this->thankYouMessage = (string) ($results['thank_you_message'] ?? '');
        $this->redirectUrl = (string) ($results['redirect_url'] ?? '');
        $this->grades = collect($results['grades'] ?? [])
            ->map(fn (array $band) => $band['min'].':'.$band['label'])
            ->implode("\n");
        $this->mode = in_array($results['mode'] ?? null, [ResultResolver::MODE_SCORE, ResultResolver::MODE_CATEGORY], true)
            ? $results['mode']
            : ResultResolver::MODE_SIMPLE;

        $this->outcomes = array_map(fn (array $outcome) => [
            'key' => $outcome['key'] ?? (string) Str::uuid(),
            'title' => (string) ($outcome['title'] ?? ''),
            'description' => (string) ($outcome['description'] ?? ''),
            'min' => (string) ($outcome['min'] ?? ''),
            'max' => (string) ($outcome['max'] ?? ''),
            'category' => (string) ($outcome['category'] ?? ''),
            'redirect_url' => (string) ($outcome['redirect_url'] ?? ''),
        ], array_values(array_filter($results['outcomes'] ?? [], 'is_array')));
    }

    public function setMode(string $mode): void
    {
        $this->authorize('update', $this->quiz);

        if (! in_array($mode, [ResultResolver::MODE_SIMPLE, ResultResolver::MODE_SCORE, ResultResolver::MODE_CATEGORY], true)) {
            return;
        }

        $this->mode = $mode;

        // Score-based screens are meaningless without scoring turned on.
        if ($mode === ResultResolver::MODE_SCORE) {
            $this->scored = true;
        }

        if ($this->outcomes === [] && $mode !== ResultResolver::MODE_SIMPLE) {
            $this->addOutcome();
        }
    }

    public function addOutcome(): void
    {
        $this->authorize('update', $this->quiz);

        // Suggest a band that continues where the last one stopped.
        $lastMax = collect($this->outcomes)->max(fn (array $o) => (float) ($o['max'] ?: 0));

        $this->outcomes[] = [
            'key' => (string) Str::uuid(),
            'title' => '',
            'description' => '',
            'min' => $this->mode === ResultResolver::MODE_SCORE ? (string) min(100, (int) $lastMax + ($this->outcomes === [] ? 0 : 1)) : '',
            'max' => $this->mode === ResultResolver::MODE_SCORE ? '100' : '',
            'category' => '',
            'redirect_url' => '',
        ];
    }

    public function removeOutcome(int $index): void
    {
        $this->authorize('update', $this->quiz);

        unset($this->outcomes[$index]);
        $this->outcomes = array_values($this->outcomes);
    }

    public function moveOutcome(int $index, int $direction): void
    {
        $this->authorize('update', $this->quiz);

        $target = $index + $direction;

        if (! isset($this->outcomes[$index], $this->outcomes[$target])) {
            return;
        }

        [$this->outcomes[$index], $this->outcomes[$target]] = [$this->outcomes[$target], $this->outcomes[$index]];
    }

    public function save(HtmlSanitizer $sanitizer): void
    {
        $this->authorize('update', $this->quiz);

        $this->validate([
            'passPercentage' => ['nullable', 'numeric', 'between:0,100'],
            'thankYouMessage' => ['nullable', 'string', 'max:2000'],
            'redirectUrl' => ['nullable', 'url', 'max:500'],
            'outcomes' => ['array', 'max:30'],
            'outcomes.*.title' => ['nullable', 'string', 'max:150'],
            'outcomes.*.min' => ['nullable', 'numeric', 'between:0,100'],
            'outcomes.*.max' => ['nullable', 'numeric', 'between:0,100'],
            'outcomes.*.category' => ['nullable', 'string', 'max:80'],
            'outcomes.*.redirect_url' => ['nullable', 'url', 'max:500'],
        ], [], [
            'outcomes.*.title' => __('result title'),
            'outcomes.*.min' => __('minimum score'),
            'outcomes.*.max' => __('maximum score'),
            'outcomes.*.category' => __('category'),
            'outcomes.*.redirect_url' => __('redirect URL'),
        ]);

        // Drop rows the author started and abandoned.
        $outcomes = collect($this->outcomes)
            ->filter(fn (array $outcome) => trim($outcome['title']) !== '' || trim(strip_tags($outcome['description'])) !== '')
            ->map(fn (array $outcome) => [
                'key' => $outcome['key'],
                'title' => trim($outcome['title']),
                // Author-written HTML rendered unescaped to respondents:
                // whitelist it before it is ever stored.
                'description' => $sanitizer->clean($outcome['description']),
                'min' => $outcome['min'] === '' ? null : (float) $outcome['min'],
                'max' => $outcome['max'] === '' ? null : (float) $outcome['max'],
                'category' => trim($outcome['category']) ?: null,
                'redirect_url' => trim($outcome['redirect_url']) ?: null,
            ])
            ->values()
            ->all();

        // Grade bands: one "min%:Label" per line, malformed lines dropped.
        $grades = collect(explode("\n", $this->grades))
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => $line !== '')
            ->map(function (string $line) {
                [$min, $label] = array_pad(explode(':', $line, 2), 2, '');

                return is_numeric(trim($min)) && trim($label) !== ''
                    ? ['min' => (float) trim($min), 'label' => mb_substr(trim($label), 0, 100)]
                    : null;
            })
            ->filter()
            ->values()
            ->all();

        $settings = $this->quiz->settings ?? [];
        $settings['scored'] = $this->scored;
        $settings['results'] = [
            ...($settings['results'] ?? []),
            'mode' => $this->mode,
            'show_score' => $this->showScore,
            'pass_percentage' => $this->passPercentage === '' ? null : (float) $this->passPercentage,
            'grades' => $grades,
            'thank_you_message' => trim($this->thankYouMessage) ?: null,
            'redirect_url' => trim($this->redirectUrl) ?: null,
            'outcomes' => $outcomes,
        ];

        $this->quiz->update(['settings' => $settings]);
        $this->quiz->refresh();

        $this->load();
        $this->dispatch('results-saved');
    }

    public function with(): array
    {
        // Categories already in use in the builder, so authors pick from what
        // exists rather than retyping (and mistyping) them.
        $categories = $this->quiz->questions()
            ->with('options')
            ->get()
            ->flatMap(fn ($question) => $question->options->pluck('settings'))
            ->map(fn ($settings) => trim((string) (($settings ?? [])['category'] ?? '')))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return [
            'canEdit' => Auth::user()->can('update', $this->quiz),
            'responseCount' => $this->quiz->responses()->count(),
            'usedCategories' => $categories,
            'choiceQuestionCount' => $this->quiz->questions()
                ->whereIn('type', [QuestionType::SingleChoice->value, QuestionType::MultipleChoice->value, QuestionType::Dropdown->value, QuestionType::ImageChoice->value])
                ->count(),
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
            <span>{{ __('What respondents see after they submit') }}</span>
        </x-slot:meta>

        @if ($canEdit)
            <x-action-message on="results-saved" class="text-xs font-semibold text-teal-600 dark:text-teal-400">{{ __('Saved') }}</x-action-message>
            <flux:button wire:click="save" variant="primary" icon="check">{{ __('Save results') }}</flux:button>
        @endif

        <x-slot:tabs>
            <x-quiz-nav :quiz="$quiz" :response-count="$responseCount" />
        </x-slot:tabs>
    </x-page-header>

    {{-- Mode picker: three cards, because the choice changes the whole
         shape of the page below it. --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @php
            $modes = [
                [
                    'key' => 'simple',
                    'icon' => 'chat-bubble-bottom-center-text',
                    'title' => __('One message'),
                    'text' => __('Everyone sees the same thank-you note.'),
                ],
                [
                    'key' => 'score',
                    'icon' => 'chart-bar',
                    'title' => __('By score'),
                    'text' => __('A different screen for each score range.'),
                ],
                [
                    'key' => 'category',
                    'icon' => 'tag',
                    'title' => __('By category'),
                    'text' => __('Match the category they picked most often.'),
                ],
            ];
        @endphp

        @foreach ($modes as $option)
            <button
                type="button"
                wire:click="setMode('{{ $option['key'] }}')"
                @disabled(! $canEdit)
                @class([
                    'flex items-start gap-3 rounded-xl border p-4 text-left transition',
                    'border-teal-600 bg-teal-50/70 ring-1 ring-teal-600 dark:border-teal-500 dark:bg-teal-950/40 dark:ring-teal-500' => $mode === $option['key'],
                    'border-zinc-200 bg-white hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700' => $mode !== $option['key'],
                    'cursor-not-allowed opacity-60' => ! $canEdit,
                ])
                aria-pressed="{{ $mode === $option['key'] ? 'true' : 'false' }}"
            >
                <span @class([
                    'flex size-9 shrink-0 items-center justify-center rounded-lg',
                    'bg-teal-600 text-white' => $mode === $option['key'],
                    'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => $mode !== $option['key'],
                ])>
                    <flux:icon :icon="$option['icon']" class="size-4.5" />
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-bold text-zinc-900 dark:text-white">{{ $option['title'] }}</span>
                    <span class="mt-0.5 block text-xs leading-snug text-zinc-500 dark:text-zinc-400">{{ $option['text'] }}</span>
                </span>
            </button>
        @endforeach
    </div>

    @if ($mode === 'category' && $usedCategories->isEmpty())
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900/60 dark:bg-amber-950/30">
            <div class="flex min-w-0 items-start gap-2.5">
                <flux:icon.exclamation-triangle class="mt-0.5 size-4.5 shrink-0 text-amber-600 dark:text-amber-400" />
                <p class="text-sm text-amber-900 dark:text-amber-200">
                    {{ $choiceQuestionCount === 0
                        ? __('Category results need choice questions. Add some in the builder, then tag each answer option with a category.')
                        : __('No answer options are tagged with a category yet. Open the builder and give each option a category so responses can be matched.') }}
                </p>
            </div>
            <flux:button :href="route('quizzes.builder', $quiz)" wire:navigate size="sm" variant="filled" icon="squares-plus">
                {{ __('Open builder') }}
            </flux:button>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] xl:items-start">
        <div class="flex flex-col gap-4">
            @if ($mode === 'simple')
                <x-panel :title="__('Thank-you message')" icon="chat-bubble-bottom-center-text" :description="__('Shown to everyone who submits.')">
                    <flux:textarea
                        wire:model="thankYouMessage"
                        rows="4"
                        :placeholder="__('Thanks for taking part — we\'ll be in touch shortly.')"
                        :disabled="! $canEdit"
                        :aria-label="__('Thank-you message')"
                    />
                </x-panel>
            @else
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold tracking-tight text-zinc-900 dark:text-white">
                                {{ $mode === 'score' ? __('Score bands') : __('Category results') }}
                            </h2>
                            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $mode === 'score'
                                    ? __('Each band gets its own screen. The highest matching band wins.')
                                    : __('Each category gets its own screen, matched to the category chosen most often.') }}
                            </p>
                        </div>

                        @if ($canEdit)
                            <flux:button wire:click="addOutcome" size="sm" variant="filled" icon="plus">
                                {{ __('Add result') }}
                            </flux:button>
                        @endif
                    </div>

                    @forelse ($outcomes as $index => $outcome)
                        <x-panel wire:key="outcome-{{ $outcome['key'] }}">
                            <x-slot:actions>
                                @if ($canEdit)
                                    <flux:button wire:click="moveOutcome({{ $index }}, -1)" variant="subtle" size="xs" icon="chevron-up" :disabled="$loop->first" :aria-label="__('Move up')" />
                                    <flux:button wire:click="moveOutcome({{ $index }}, 1)" variant="subtle" size="xs" icon="chevron-down" :disabled="$loop->last" :aria-label="__('Move down')" />
                                    <flux:button wire:click="removeOutcome({{ $index }})" variant="subtle" size="xs" icon="trash" :aria-label="__('Remove this result')" />
                                @endif
                            </x-slot:actions>

                            <x-slot:title>
                                {{ trim($outcome['title']) !== '' ? $outcome['title'] : __('Result :n', ['n' => $index + 1]) }}
                            </x-slot:title>

                            <div class="flex flex-col gap-5">
                                @if ($mode === 'score')
                                    <div class="grid grid-cols-2 gap-4">
                                        <flux:input
                                            wire:model="outcomes.{{ $index }}.min"
                                            :label="__('From (%)')"
                                            type="number"
                                            min="0"
                                            max="100"
                                            placeholder="0"
                                            :disabled="! $canEdit"
                                        />
                                        <flux:input
                                            wire:model="outcomes.{{ $index }}.max"
                                            :label="__('To (%)')"
                                            type="number"
                                            min="0"
                                            max="100"
                                            placeholder="100"
                                            :disabled="! $canEdit"
                                        />
                                    </div>
                                @else
                                    <div>
                                        <flux:input
                                            wire:model="outcomes.{{ $index }}.category"
                                            :label="__('Category')"
                                            type="text"
                                            :placeholder="__('e.g. Planner')"
                                            list="qf-categories"
                                            :disabled="! $canEdit"
                                            :description="__('Must match a category you set on answer options in the builder.')"
                                        />
                                    </div>
                                @endif

                                <flux:input
                                    wire:model="outcomes.{{ $index }}.title"
                                    :label="__('Title')"
                                    type="text"
                                    :placeholder="$mode === 'score' ? __('e.g. Excellent work!') : __('e.g. You\'re a Planner')"
                                    :disabled="! $canEdit"
                                />

                                <x-rich-text
                                    :model="'outcomes.'.$index.'.description'"
                                    :label="__('Description')"
                                    :placeholder="__('Explain what this result means…')"
                                    :description="__('Bold, italics, headings, lists and links are supported.')"
                                    rows="5"
                                >{!! $outcome['description'] !!}</x-rich-text>

                                <flux:input
                                    wire:model="outcomes.{{ $index }}.redirect_url"
                                    :label="__('Redirect URL')"
                                    type="url"
                                    placeholder="https://example.com/next-steps"
                                    :disabled="! $canEdit"
                                    :description="__('Optional. Overrides the quiz-wide redirect for this result only.')"
                                />
                            </div>
                        </x-panel>
                    @empty
                        <div class="qf-surface">
                            <x-empty-state
                                compact
                                icon="sparkles"
                                :title="__('No results yet')"
                                :description="$mode === 'score'
                                    ? __('Add a band for each score range you want to speak to — for example 0–49% and 50–100%.')
                                    : __('Add one result per category so every respondent lands somewhere.')"
                            >
                                @if ($canEdit)
                                    <flux:button wire:click="addOutcome" variant="primary" icon="plus">{{ __('Add your first result') }}</flux:button>
                                @endif
                            </x-empty-state>
                        </div>
                    @endforelse

                    <datalist id="qf-categories">
                        @foreach ($usedCategories as $category)
                            <option value="{{ $category }}"></option>
                        @endforeach
                    </datalist>

                    <x-panel :title="__('Fallback message')" icon="lifebuoy" :description="__('Used when no result above matches.')">
                        <flux:textarea
                            wire:model="thankYouMessage"
                            rows="3"
                            :placeholder="__('Thanks for taking part!')"
                            :disabled="! $canEdit"
                            :aria-label="__('Fallback thank-you message')"
                        />
                    </x-panel>
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-4">
            <x-panel :title="__('Scoring')" icon="academic-cap">
                <div class="flex flex-col gap-4">
                    <div class="qf-well flex flex-col gap-3 p-4">
                        <flux:checkbox
                            wire:model.live="scored"
                            :label="__('Score this quiz')"
                            :description="__('Award points for correct answers.')"
                            :disabled="! $canEdit || $mode === 'score'"
                        />
                        @if ($scored)
                            <flux:checkbox wire:model="showScore" :label="__('Show the score to respondents')" :disabled="! $canEdit" />
                        @endif
                    </div>

                    @if ($mode === 'score')
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Score-based results need scoring, so it stays on while this mode is selected.') }}
                        </p>
                    @endif

                    @if ($scored)
                        <flux:input
                            wire:model="passPercentage"
                            :label="__('Pass mark (%)')"
                            type="number"
                            min="0"
                            max="100"
                            :placeholder="__('e.g. 60 — leave empty for none')"
                            :disabled="! $canEdit"
                        />

                        <flux:textarea
                            wire:model="grades"
                            :label="__('Grade bands')"
                            rows="4"
                            placeholder="80:Gold&#10;50:Silver&#10;0:Bronze"
                            :disabled="! $canEdit"
                            :description="__('One per line as minimum%:Label. Shown as a small badge next to the score.')"
                        />
                    @endif
                </div>
            </x-panel>

            <x-panel :title="__('Redirect')" icon="arrow-top-right-on-square" :description="__('Where everyone goes next, unless a result overrides it.')">
                <flux:input
                    wire:model="redirectUrl"
                    type="url"
                    placeholder="https://example.com/thanks"
                    :disabled="! $canEdit"
                    :aria-label="__('Quiz-wide redirect URL')"
                />
            </x-panel>

            <div class="qf-well p-4">
                <p class="qf-eyebrow mb-2">{{ __('How matching works') }}</p>
                <p class="text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">
                    @if ($mode === 'simple')
                        {{ __('Every respondent sees the same message. Switch to score or category mode to tailor it.') }}
                    @elseif ($mode === 'score')
                        {{ __('The respondent\'s percentage is checked against each band. If ranges overlap, the band with the higher starting point wins. No match falls back to your message.') }}
                    @else
                        {{ __('Every option they picked adds one to its category. The category with the most picks decides the result. No tagged options means the fallback message is shown.') }}
                    @endif
                </p>
            </div>
        </div>
    </div>

    @if ($canEdit)
        <div class="flex items-center gap-4">
            <flux:button wire:click="save" variant="primary" icon="check">{{ __('Save results') }}</flux:button>
            <x-action-message on="results-saved">{{ __('Saved.') }}</x-action-message>
        </div>
    @endif
</section>
