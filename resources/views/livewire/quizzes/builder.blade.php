<?php

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizPage;
use Livewire\Volt\Component;

new class extends Component {
    public Quiz $quiz;

    public ?int $selectedQuestionId = null;
    public ?int $pickingForPageId = null;

    public string $qTitle = '';
    public string $qDescription = '';
    public string $qHelpText = '';
    public string $qPlaceholder = '';
    public bool $qRequired = false;
    public bool $qHidden = false;
    public array $qSettings = [];
    public string $qMatrixRows = '';
    public string $qMatrixColumns = '';

    /** @var array<int, string> */
    public array $pageTitles = [];

    /** @var array<int, string> */
    public array $optionLabels = [];

    public function boot(): void
    {
        if (isset($this->quiz)) {
            $this->authorize('update', $this->quiz);
        }
    }

    public function mount(Quiz $quiz): void
    {
        $this->authorize('update', $quiz);

        $this->quiz = $quiz;

        if ($quiz->pages()->count() === 0) {
            $quiz->pages()->create(['title' => __('Page 1'), 'position' => 0]);
        }

        $this->syncPageTitles();

        foreach ($quiz->pages as $page) {
            if ($first = $page->questions()->first()) {
                $this->selectQuestion($first->id);
                break;
            }
        }
    }

    // ── Selection ──────────────────────────────────────────────

    public function selectQuestion(int $questionId): void
    {
        $question = $this->quiz->questions()->with('options')->findOrFail($questionId);

        $this->selectedQuestionId = $question->id;
        $this->pickingForPageId = null;

        $this->qTitle = $question->title;
        $this->qDescription = $question->description ?? '';
        $this->qHelpText = $question->help_text ?? '';
        $this->qPlaceholder = $question->placeholder ?? '';
        $this->qRequired = $question->is_required;
        $this->qHidden = $question->is_hidden;
        $this->qSettings = $question->settings ?? [];
        $this->qMatrixRows = implode("\n", $this->qSettings['rows'] ?? []);
        $this->qMatrixColumns = implode("\n", $this->qSettings['columns'] ?? []);

        $this->syncOptionLabels($question);
        $this->resetErrorBag();
    }

    protected function clearSelection(): void
    {
        $this->selectedQuestionId = null;
        $this->optionLabels = [];
    }

    protected function selectedQuestion(): ?Question
    {
        return $this->selectedQuestionId
            ? $this->quiz->questions()->with('options')->find($this->selectedQuestionId)
            : null;
    }

    protected function syncPageTitles(): void
    {
        $this->pageTitles = $this->quiz->pages()->pluck('title', 'id')
            ->map(fn ($title) => $title ?? '')
            ->all();
    }

    protected function syncOptionLabels(Question $question): void
    {
        $this->optionLabels = $question->options()->pluck('label', 'id')->all();
    }

    // ── Autosave ───────────────────────────────────────────────

    public function updated(string $name, $value): void
    {
        if (str_starts_with($name, 'pageTitles.')) {
            $pageId = (int) \Illuminate\Support\Str::afterLast($name, '.');
            $this->quiz->pages()->findOrFail($pageId)
                ->update(['title' => trim((string) $value) !== '' ? mb_substr(trim((string) $value), 0, 150) : null]);
            $this->dispatch('builder-saved');

            return;
        }

        if (str_starts_with($name, 'optionLabels.')) {
            $optionId = (int) \Illuminate\Support\Str::afterLast($name, '.');
            $question = $this->selectedQuestion();

            $question?->options()->findOrFail($optionId)
                ->update(['label' => mb_substr((string) $value, 0, 500)]);
            $this->dispatch('builder-saved');

            return;
        }

        if (str_starts_with($name, 'qSettings.') || in_array($name, ['qMatrixRows', 'qMatrixColumns'], true)) {
            $this->persistSettings();

            return;
        }

        $map = [
            'qTitle' => 'title',
            'qDescription' => 'description',
            'qHelpText' => 'help_text',
            'qPlaceholder' => 'placeholder',
            'qRequired' => 'is_required',
            'qHidden' => 'is_hidden',
        ];

        if (isset($map[$name]) && ($question = $this->selectedQuestion())) {
            $attribute = $map[$name];

            $value = match ($attribute) {
                'is_required', 'is_hidden' => (bool) $value,
                'title' => mb_substr((string) $value, 0, 500),
                'help_text' => mb_substr((string) $value, 0, 500) ?: null,
                default => trim((string) $value) !== '' ? (string) $value : null,
            };

            $question->update([$attribute => $value]);
            $this->dispatch('builder-saved');
        }
    }

    protected function persistSettings(): void
    {
        $question = $this->selectedQuestion();

        if (! $question) {
            return;
        }

        $settings = $this->qSettings;

        $clampInt = function ($value, int $min, int $max): ?int {
            if ($value === null || $value === '') {
                return null;
            }

            return max($min, min($max, (int) $value));
        };

        $settings = match ($question->type) {
            QuestionType::Rating => ['max' => $clampInt($settings['max'] ?? 5, 2, 10) ?? 5],
            QuestionType::OpinionScale, QuestionType::LinearScale => [
                'min' => $clampInt($settings['min'] ?? 1, 0, 1) ?? 1,
                'max' => $clampInt($settings['max'] ?? 5, 2, 10) ?? 5,
                'min_label' => mb_substr((string) ($settings['min_label'] ?? ''), 0, 100),
                'max_label' => mb_substr((string) ($settings['max_label'] ?? ''), 0, 100),
            ],
            QuestionType::Nps => [
                'min_label' => mb_substr((string) ($settings['min_label'] ?? ''), 0, 100),
                'max_label' => mb_substr((string) ($settings['max_label'] ?? ''), 0, 100),
            ],
            QuestionType::ShortText, QuestionType::LongText => [
                'max_length' => $clampInt($settings['max_length'] ?? null, 1, 100000),
            ],
            QuestionType::Number => [
                'min' => ($settings['min'] ?? '') === '' ? null : (float) $settings['min'],
                'max' => ($settings['max'] ?? '') === '' ? null : (float) $settings['max'],
            ],
            QuestionType::Matrix => [
                'rows' => $this->linesToArray($this->qMatrixRows, [__('Row 1')]),
                'columns' => $this->linesToArray($this->qMatrixColumns, [__('Column 1')]),
            ],
            default => $settings,
        };

        $question->update(['settings' => $settings]);
        $this->qSettings = $settings;
        $this->dispatch('builder-saved');
    }

    protected function linesToArray(string $lines, array $fallback): array
    {
        $items = collect(explode("\n", $lines))
            ->map(fn ($line) => mb_substr(trim($line), 0, 200))
            ->filter(fn ($line) => $line !== '')
            ->values()
            ->all();

        return $items !== [] ? $items : $fallback;
    }

    // ── Pages ──────────────────────────────────────────────────

    public function addPage(): void
    {
        $count = $this->quiz->pages()->count();

        $this->quiz->pages()->create([
            'title' => __('Page :number', ['number' => $count + 1]),
            'position' => $count,
        ]);

        $this->syncPageTitles();
    }

    public function deletePage(int $pageId): void
    {
        $page = $this->quiz->pages()->findOrFail($pageId);

        if ($this->quiz->pages()->count() <= 1) {
            $this->addError('builder', __('A quiz needs at least one page.'));

            return;
        }

        $selected = $this->selectedQuestion();

        if ($selected && $selected->quiz_page_id === $page->id) {
            $this->clearSelection();
        }

        $page->delete();

        $this->reindexPages();
        $this->syncPageTitles();
    }

    public function movePage(int $pageId, int $direction): void
    {
        $pages = $this->quiz->pages()->get()->values();
        $index = $pages->search(fn (QuizPage $page) => $page->id === $pageId);

        $neighbor = $index === false ? null : ($pages[$index + $direction] ?? null);

        if ($neighbor) {
            $pages[$index]->update(['position' => $neighbor->position]);
            $neighbor->update(['position' => $index]);
            $this->reindexPages();
        }
    }

    protected function reindexPages(): void
    {
        $this->quiz->pages()->get()->values()->each(function (QuizPage $page, int $index) {
            if ($page->position !== $index) {
                $page->update(['position' => $index]);
            }
        });
    }

    // ── Questions ──────────────────────────────────────────────

    public function startPicking(int $pageId): void
    {
        $this->quiz->pages()->findOrFail($pageId);

        $this->pickingForPageId = $pageId;
        $this->clearSelection();
    }

    public function cancelPicking(): void
    {
        $this->pickingForPageId = null;
    }

    public function addQuestion(string $type): void
    {
        $questionType = QuestionType::from($type);
        $page = $this->quiz->pages()->findOrFail($this->pickingForPageId);

        $question = $page->questions()->create([
            'quiz_id' => $this->quiz->id,
            'type' => $questionType,
            'title' => '',
            'position' => $page->questions()->count(),
            'settings' => $questionType->defaultSettings(),
        ]);

        foreach ($questionType->defaultOptionLabels() as $index => $label) {
            $question->options()->create(['label' => $label, 'position' => $index]);
        }

        $this->pickingForPageId = null;
        $this->selectQuestion($question->id);
    }

    public function duplicateQuestion(int $questionId): void
    {
        $question = $this->quiz->questions()->findOrFail($questionId);

        $copy = $question->duplicate();

        $this->reindexQuestions($question->page);
        $this->selectQuestion($copy->id);
    }

    public function deleteQuestion(int $questionId): void
    {
        $question = $this->quiz->questions()->findOrFail($questionId);
        $page = $question->page;

        if ($this->selectedQuestionId === $question->id) {
            $this->clearSelection();
        }

        $question->delete();

        $this->reindexQuestions($page);
    }

    public function moveQuestion(int $questionId, int $direction): void
    {
        $question = $this->quiz->questions()->findOrFail($questionId);
        $siblings = $question->page->questions()->get()->values();
        $index = $siblings->search(fn (Question $sibling) => $sibling->id === $question->id);

        $neighbor = $index === false ? null : ($siblings[$index + $direction] ?? null);

        if ($neighbor) {
            $siblings[$index]->update(['position' => $neighbor->position]);
            $neighbor->update(['position' => $index]);
            $this->reindexQuestions($question->page);
        }
    }

    protected function reindexQuestions(QuizPage $page): void
    {
        $page->questions()->get()->values()->each(function (Question $question, int $index) {
            if ($question->position !== $index) {
                $question->update(['position' => $index]);
            }
        });
    }

    // ── Options ────────────────────────────────────────────────

    public function addOption(): void
    {
        $question = $this->selectedQuestion();

        if (! $question || ! $question->type->hasOptions()) {
            return;
        }

        $question->options()->create([
            'label' => __('Option :number', ['number' => $question->options()->count() + 1]),
            'position' => $question->options()->count(),
        ]);

        $this->syncOptionLabels($question);
    }

    public function removeOption(int $optionId): void
    {
        $question = $this->selectedQuestion();

        if (! $question) {
            return;
        }

        if ($question->options()->count() <= 1) {
            $this->addError('options', __('A choice question needs at least one option.'));

            return;
        }

        $question->options()->findOrFail($optionId)->delete();

        $question->options()->get()->values()->each(function ($option, int $index) {
            if ($option->position !== $index) {
                $option->update(['position' => $index]);
            }
        });

        $this->syncOptionLabels($question->refresh());
    }

    public function moveOption(int $optionId, int $direction): void
    {
        $question = $this->selectedQuestion();

        if (! $question) {
            return;
        }

        $options = $question->options()->get()->values();
        $index = $options->search(fn ($option) => $option->id === $optionId);
        $neighbor = $index === false ? null : ($options[$index + $direction] ?? null);

        if ($neighbor) {
            $options[$index]->update(['position' => $neighbor->position]);
            $neighbor->update(['position' => $index]);
        }
    }

    public function toggleCorrect(int $optionId): void
    {
        $question = $this->selectedQuestion();

        if (! $question || ! $question->type->supportsCorrectAnswers()) {
            return;
        }

        $option = $question->options()->findOrFail($optionId);

        if ($question->type === QuestionType::MultipleChoice) {
            $option->update(['is_correct' => ! $option->is_correct]);
        } else {
            $makingCorrect = ! $option->is_correct;
            $question->options()->update(['is_correct' => false]);

            if ($makingCorrect) {
                $option->update(['is_correct' => true]);
            }
        }
    }

    public function with(): array
    {
        return [
            'pages' => $this->quiz->pages()->with('questions.options')->get(),
            'selected' => $this->selectedQuestion(),
            'typeGroups' => QuestionType::grouped(),
        ];
    }
}; ?>

<section class="flex w-full flex-col gap-6 lg:flex-row">
    {{-- Structure panel --}}
    <aside class="w-full shrink-0 lg:w-80">
        <div class="mb-4 flex items-center justify-between gap-2">
            <div class="min-w-0">
                <a href="{{ route('quizzes.show', $quiz) }}" wire:navigate class="flex items-center gap-1 text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                    <flux:icon.arrow-left class="size-3.5" />
                    {{ __('Back to overview') }}
                </a>
                <flux:heading class="mt-1 truncate">{{ $quiz->name }}</flux:heading>
            </div>

            <x-action-message on="builder-saved" class="shrink-0 text-xs">
                {{ __('Saved') }}
            </x-action-message>
        </div>

        @error('builder')
            <flux:text class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror

        <div class="space-y-4">
            @foreach ($pages as $page)
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700" wire:key="page-{{ $page->id }}">
                    <div class="flex items-center gap-1 border-b border-zinc-200 p-2 dark:border-zinc-700">
                        <input
                            type="text"
                            wire:model.blur="pageTitles.{{ $page->id }}"
                            placeholder="{{ __('Page :number', ['number' => $loop->iteration]) }}"
                            aria-label="{{ __('Page title') }}"
                            class="w-full min-w-0 flex-1 border-0 bg-transparent p-1 text-sm font-medium text-zinc-800 focus:ring-0 dark:text-white"
                        />

                        <flux:button variant="subtle" size="xs" icon="chevron-up" wire:click="movePage({{ $page->id }}, -1)" :disabled="$loop->first" aria-label="{{ __('Move page up') }}" />
                        <flux:button variant="subtle" size="xs" icon="chevron-down" wire:click="movePage({{ $page->id }}, 1)" :disabled="$loop->last" aria-label="{{ __('Move page down') }}" />
                        <flux:button
                            variant="subtle"
                            size="xs"
                            icon="trash"
                            wire:click="deletePage({{ $page->id }})"
                            wire:confirm="{{ __('Delete this page and all of its questions?') }}"
                            aria-label="{{ __('Delete page') }}"
                        />
                    </div>

                    <ul>
                        @foreach ($page->questions as $question)
                            <li wire:key="question-{{ $question->id }}">
                                <button
                                    type="button"
                                    wire:click="selectQuestion({{ $question->id }})"
                                    @class([
                                        'flex w-full items-center gap-2 px-3 py-2 text-left text-sm',
                                        'bg-violet-50 text-violet-900 dark:bg-violet-950/50 dark:text-violet-200' => $selectedQuestionId === $question->id,
                                        'text-zinc-700 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800/60' => $selectedQuestionId !== $question->id,
                                    ])
                                >
                                    <flux:icon :icon="$question->type->icon()" class="size-4 shrink-0 text-zinc-400" />
                                    <span class="min-w-0 flex-1 truncate">
                                        {{ $question->title !== '' ? $question->title : __('Untitled question') }}
                                    </span>
                                    @if ($question->is_hidden)
                                        <flux:icon.eye-slash class="size-3.5 shrink-0 text-zinc-400" />
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="p-2">
                        <flux:button variant="subtle" size="sm" icon="plus" wire:click="startPicking({{ $page->id }})" class="w-full justify-start">
                            {{ __('Add question') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach

            <flux:button variant="filled" icon="plus" wire:click="addPage" class="w-full">
                {{ __('Add page') }}
            </flux:button>
        </div>
    </aside>

    {{-- Editor panel --}}
    <div class="min-w-0 flex-1">
        @if ($pickingForPageId)
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Choose a question type') }}</flux:heading>
                <flux:button variant="subtle" size="sm" wire:click="cancelPicking">{{ __('Cancel') }}</flux:button>
            </div>

            <div class="mt-6 space-y-6">
                @foreach ($typeGroups as $category => $types)
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $category }}</p>

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($types as $type)
                                <button
                                    type="button"
                                    wire:click="addQuestion('{{ $type->value }}')"
                                    wire:key="qtype-{{ $type->value }}"
                                    class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 text-left transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-600 dark:hover:bg-zinc-800/60"
                                >
                                    <flux:icon :icon="$type->icon()" class="size-5 shrink-0 text-zinc-500 dark:text-zinc-400" />
                                    <span class="text-sm font-medium text-zinc-800 dark:text-white">{{ $type->label() }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif ($selected)
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon :icon="$selected->type->icon()" class="size-4 text-zinc-500 dark:text-zinc-400" />
                    </span>
                    <flux:heading>{{ $selected->type->label() }}</flux:heading>
                </div>

                <div class="flex items-center gap-1">
                    <flux:button variant="subtle" size="sm" icon="chevron-up" wire:click="moveQuestion({{ $selected->id }}, -1)" aria-label="{{ __('Move question up') }}" />
                    <flux:button variant="subtle" size="sm" icon="chevron-down" wire:click="moveQuestion({{ $selected->id }}, 1)" aria-label="{{ __('Move question down') }}" />
                    <flux:button variant="subtle" size="sm" icon="document-duplicate" wire:click="duplicateQuestion({{ $selected->id }})" aria-label="{{ __('Duplicate question') }}" />
                    <flux:button
                        variant="subtle"
                        size="sm"
                        icon="trash"
                        wire:click="deleteQuestion({{ $selected->id }})"
                        wire:confirm="{{ __('Delete this question?') }}"
                        aria-label="{{ __('Delete question') }}"
                    />
                </div>
            </div>

            <div class="mt-6 max-w-2xl space-y-5">
                <flux:input
                    wire:model.live.debounce.500ms="qTitle"
                    label="{{ __('Question title') }}"
                    placeholder="{{ __('Type your question…') }}"
                />

                <flux:textarea
                    wire:model.live.debounce.500ms="qDescription"
                    label="{{ __('Description') }}"
                    rows="2"
                    placeholder="{{ __('Add extra context (optional)') }}"
                />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model.live.debounce.500ms="qHelpText"
                        label="{{ __('Help text') }}"
                        placeholder="{{ __('Shown below the answer field') }}"
                    />

                    @if ($selected->type->supportsPlaceholder())
                        <flux:input
                            wire:model.live.debounce.500ms="qPlaceholder"
                            label="{{ __('Placeholder') }}"
                            placeholder="{{ __('e.g. Type here…') }}"
                        />
                    @endif
                </div>

                <div class="flex items-center gap-6">
                    <flux:checkbox wire:model.live="qRequired" label="{{ __('Required') }}" />
                    <flux:checkbox wire:model.live="qHidden" label="{{ __('Hidden') }}" />
                </div>

                @if ($selected->type->hasOptions())
                    <flux:separator />

                    <div>
                        <div class="flex items-center justify-between">
                            <flux:heading>{{ __('Options') }}</flux:heading>
                            @if ($selected->type->supportsCorrectAnswers())
                                <flux:subheading class="text-xs">{{ __('Tick an option to mark it correct') }}</flux:subheading>
                            @endif
                        </div>

                        @error('options')
                            <flux:text class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                        @enderror

                        <ul class="mt-3 space-y-2">
                            @foreach ($selected->options as $option)
                                <li class="flex items-center gap-2" wire:key="option-{{ $option->id }}">
                                    @if ($selected->type->supportsCorrectAnswers())
                                        <input
                                            type="checkbox"
                                            wire:click="toggleCorrect({{ $option->id }})"
                                            @checked($option->is_correct)
                                            aria-label="{{ __('Mark as correct answer') }}"
                                            class="size-4 rounded border-zinc-300 text-green-600 focus:ring-green-500 dark:border-zinc-600 dark:bg-zinc-800"
                                        />
                                    @endif

                                    <input
                                        type="text"
                                        wire:model.blur="optionLabels.{{ $option->id }}"
                                        aria-label="{{ __('Option label') }}"
                                        class="w-full flex-1 rounded-lg border-zinc-300 bg-white text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
                                    />

                                    <flux:button variant="subtle" size="xs" icon="chevron-up" wire:click="moveOption({{ $option->id }}, -1)" :disabled="$loop->first" aria-label="{{ __('Move option up') }}" />
                                    <flux:button variant="subtle" size="xs" icon="chevron-down" wire:click="moveOption({{ $option->id }}, 1)" :disabled="$loop->last" aria-label="{{ __('Move option down') }}" />
                                    <flux:button variant="subtle" size="xs" icon="x-mark" wire:click="removeOption({{ $option->id }})" aria-label="{{ __('Remove option') }}" />
                                </li>
                            @endforeach
                        </ul>

                        <flux:button variant="subtle" size="sm" icon="plus" wire:click="addOption" class="mt-3">
                            {{ __('Add option') }}
                        </flux:button>
                    </div>
                @endif

                @if (in_array($selected->type, [\App\Enums\QuestionType::Rating, \App\Enums\QuestionType::OpinionScale, \App\Enums\QuestionType::LinearScale, \App\Enums\QuestionType::Nps, \App\Enums\QuestionType::ShortText, \App\Enums\QuestionType::LongText, \App\Enums\QuestionType::Number, \App\Enums\QuestionType::Matrix], true))
                    <flux:separator />

                    <flux:heading>{{ __('Type settings') }}</flux:heading>

                    @if ($selected->type === \App\Enums\QuestionType::Rating)
                        <flux:input wire:model.live.debounce.500ms="qSettings.max" label="{{ __('Number of stars (2–10)') }}" type="number" min="2" max="10" class="max-w-40" />
                    @elseif (in_array($selected->type, [\App\Enums\QuestionType::OpinionScale, \App\Enums\QuestionType::LinearScale], true))
                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model.live.debounce.500ms="qSettings.min" label="{{ __('Scale starts at (0 or 1)') }}" type="number" min="0" max="1" />
                            <flux:input wire:model.live.debounce.500ms="qSettings.max" label="{{ __('Scale ends at (2–10)') }}" type="number" min="2" max="10" />
                            <flux:input wire:model.live.debounce.500ms="qSettings.min_label" label="{{ __('Left label') }}" placeholder="{{ __('e.g. Strongly disagree') }}" />
                            <flux:input wire:model.live.debounce.500ms="qSettings.max_label" label="{{ __('Right label') }}" placeholder="{{ __('e.g. Strongly agree') }}" />
                        </div>
                    @elseif ($selected->type === \App\Enums\QuestionType::Nps)
                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model.live.debounce.500ms="qSettings.min_label" label="{{ __('Label for 0') }}" />
                            <flux:input wire:model.live.debounce.500ms="qSettings.max_label" label="{{ __('Label for 10') }}" />
                        </div>
                    @elseif (in_array($selected->type, [\App\Enums\QuestionType::ShortText, \App\Enums\QuestionType::LongText], true))
                        <flux:input wire:model.live.debounce.500ms="qSettings.max_length" label="{{ __('Character limit (optional)') }}" type="number" min="1" class="max-w-40" />
                    @elseif ($selected->type === \App\Enums\QuestionType::Number)
                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model.live.debounce.500ms="qSettings.min" label="{{ __('Minimum (optional)') }}" type="number" />
                            <flux:input wire:model.live.debounce.500ms="qSettings.max" label="{{ __('Maximum (optional)') }}" type="number" />
                        </div>
                    @elseif ($selected->type === \App\Enums\QuestionType::Matrix)
                        <div class="grid grid-cols-2 gap-4">
                            <flux:textarea wire:model.live.debounce.800ms="qMatrixRows" label="{{ __('Rows (one per line)') }}" rows="4" />
                            <flux:textarea wire:model.live.debounce.800ms="qMatrixColumns" label="{{ __('Columns (one per line)') }}" rows="4" />
                        </div>
                    @endif
                @endif
            </div>
        @else
            <div class="flex h-full min-h-64 flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
                <flux:icon.cursor-arrow-rays class="size-8 text-zinc-400" />
                <flux:heading class="mt-3">{{ __('Select a question to edit it') }}</flux:heading>
                <flux:subheading class="max-w-sm">
                    {{ __('Or add a new question to any page from the panel on the left. Changes save automatically.') }}
                </flux:subheading>
            </div>
        @endif
    </div>
</section>
