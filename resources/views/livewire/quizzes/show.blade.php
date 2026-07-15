<?php

use App\Actions\Quizzes\PublishQuiz;
use App\Enums\QuizStatus;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public Quiz $quiz;
    public string $name = '';
    public string $description = '';

    public function mount(Quiz $quiz): void
    {
        $this->authorize('view', $quiz);

        $this->quiz = $quiz;
        $this->name = $quiz->name;
        $this->description = $quiz->description ?? '';

        $this->loadResultSettings();
    }

    public function updateDetails(): void
    {
        $this->authorize('update', $this->quiz);

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->quiz->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
        ]);

        $this->dispatch('quiz-updated');
    }

    public function duplicate(): void
    {
        $this->authorize('create', Quiz::class);

        $copy = $this->quiz->duplicate(Auth::user());

        $this->redirectRoute('quizzes.show', $copy, navigate: true);
    }

    public function archive(): void
    {
        $this->authorize('update', $this->quiz);

        $this->quiz->archive();
    }

    public function unarchive(): void
    {
        $this->authorize('update', $this->quiz);

        $this->quiz->unarchive();
    }

    public bool $resultScored = false;
    public bool $resultShowScore = true;
    public string $resultPassPercentage = '';
    public string $resultGrades = '';
    public string $resultMessage = '';
    public string $resultRedirect = '';

    public function loadResultSettings(): void
    {
        $settings = $this->quiz->settings ?? [];
        $results = $settings['results'] ?? [];

        $this->resultScored = (bool) ($settings['scored'] ?? false);
        $this->resultShowScore = (bool) ($results['show_score'] ?? true);
        $this->resultPassPercentage = (string) ($results['pass_percentage'] ?? '');
        $this->resultGrades = collect($results['grades'] ?? [])
            ->map(fn (array $band) => $band['min'].':'.$band['label'])
            ->implode("\n");
        $this->resultMessage = (string) ($results['thank_you_message'] ?? '');
        $this->resultRedirect = (string) ($results['redirect_url'] ?? '');
    }

    public function saveResults(): void
    {
        $this->authorize('update', $this->quiz);

        $validated = $this->validate([
            'resultPassPercentage' => ['nullable', 'numeric', 'between:0,100'],
            'resultMessage' => ['nullable', 'string', 'max:2000'],
            'resultRedirect' => ['nullable', 'url', 'max:500'],
        ]);

        $grades = collect(explode("\n", $this->resultGrades))
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
        $settings['scored'] = $this->resultScored;
        $settings['results'] = [
            'show_score' => $this->resultShowScore,
            'pass_percentage' => $this->resultPassPercentage === '' ? null : (float) $this->resultPassPercentage,
            'grades' => $grades,
            'thank_you_message' => trim($this->resultMessage) ?: null,
            'redirect_url' => trim($this->resultRedirect) ?: null,
        ];

        $this->quiz->update(['settings' => $settings]);

        $this->loadResultSettings();
        $this->dispatch('results-saved');
    }

    public function publish(PublishQuiz $publishQuiz): void
    {
        $this->authorize('update', $this->quiz);

        $publishQuiz->handle($this->quiz, Auth::user());

        $this->quiz->refresh();
        $this->dispatch('quiz-published');
    }

    public function closeQuiz(): void
    {
        $this->authorize('update', $this->quiz);

        if ($this->quiz->status === QuizStatus::Published) {
            $this->quiz->update(['status' => QuizStatus::Closed]);
        }
    }

    public function reopen(): void
    {
        $this->authorize('update', $this->quiz);

        if ($this->quiz->status === QuizStatus::Closed && $this->quiz->versions()->exists()) {
            $this->quiz->update(['status' => QuizStatus::Published]);
        }
    }

    public function deleteQuiz(): void
    {
        $this->authorize('delete', $this->quiz);

        if (! $this->quiz->isArchived()) {
            $this->addError('actions', __('Archive a quiz before deleting it.'));

            return;
        }

        $this->quiz->delete();

        $this->redirectRoute('quizzes.index', navigate: true);
    }

    public function with(): array
    {
        return [
            'canEdit' => Auth::user()->can('update', $this->quiz),
        ];
    }
}; ?>

<section class="mx-auto w-full max-w-3xl">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <flux:heading size="xl" class="truncate">{{ $quiz->name }}</flux:heading>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $quiz->status->badgeClasses() }}">
                    {{ $quiz->status->label() }}
                </span>
            </div>
            <flux:subheading>
                {{ $quiz->type->label() }}
                &middot;
                {{ __('Created :time', ['time' => $quiz->created_at->diffForHumans()]) }}
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button href="{{ route('quizzes.preview', $quiz) }}" target="_blank" variant="filled" icon="eye">{{ __('Preview') }}</flux:button>

            @if ($canEdit)
                <flux:button :href="route('quizzes.builder', $quiz)" wire:navigate variant="primary" icon="squares-plus">{{ __('Open builder') }}</flux:button>
                <flux:button wire:click="duplicate" variant="filled" icon="document-duplicate">{{ __('Duplicate') }}</flux:button>

                @if ($quiz->isArchived())
                    <flux:button wire:click="unarchive" variant="filled" icon="arrow-uturn-left">{{ __('Restore to draft') }}</flux:button>
                    <flux:button
                        wire:click="deleteQuiz"
                        wire:confirm="{{ __('Permanently delete this quiz? This cannot be undone.') }}"
                        variant="danger"
                        icon="trash"
                    >
                        {{ __('Delete') }}
                    </flux:button>
                @else
                    <flux:button
                        wire:click="archive"
                        wire:confirm="{{ __('Archive this quiz? It will stop accepting responses.') }}"
                        variant="filled"
                        icon="archive-box"
                    >
                        {{ __('Archive') }}
                    </flux:button>
                @endif
            @endif
        </div>
    </div>

    @error('actions')
        <flux:text class="mt-4 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
    @enderror

    @if ($canEdit)
        <form wire:submit="updateDetails" class="mt-8 max-w-lg space-y-6">
            <flux:input
                wire:model="name"
                label="{{ __('Name') }}"
                type="text"
                required
            />

            <flux:textarea
                wire:model="description"
                label="{{ __('Description') }}"
                rows="3"
                placeholder="{{ __('Internal notes about this quiz (optional)') }}"
            />

            <flux:input
                value="{{ $quiz->slug }}"
                label="{{ __('Public link') }}"
                type="text"
                disabled
                description="{{ __('Your quiz will be available at /q/:slug once published.', ['slug' => $quiz->slug]) }}"
            />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>

                <x-action-message class="me-3" on="quiz-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    @endif

    @if ($canEdit)
        <div class="mt-10 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <flux:heading>{{ __('Sharing') }}</flux:heading>
                    <flux:subheading>
                        @if ($quiz->status === QuizStatus::Published)
                            {{ __('Live on version :version. Edits stay private until you republish.', ['version' => $quiz->latestVersion()?->version]) }}
                        @elseif ($quiz->status === QuizStatus::Closed)
                            {{ __('Closed — the public link shows a "no longer accepting responses" notice.') }}
                        @else
                            {{ __('Publish to get a public link you can share anywhere.') }}
                        @endif
                    </flux:subheading>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($quiz->status === QuizStatus::Published)
                        <flux:button wire:click="publish" variant="filled" icon="arrow-path">{{ __('Republish changes') }}</flux:button>
                        <flux:button wire:click="closeQuiz" wire:confirm="{{ __('Stop accepting responses?') }}" variant="filled" icon="lock-closed">{{ __('Close') }}</flux:button>
                    @elseif ($quiz->status === QuizStatus::Closed)
                        <flux:button wire:click="reopen" variant="primary" icon="lock-open">{{ __('Reopen') }}</flux:button>
                        <flux:button wire:click="publish" variant="filled" icon="arrow-path">{{ __('Republish changes') }}</flux:button>
                    @elseif ($quiz->status === QuizStatus::Draft)
                        <flux:button wire:click="publish" variant="primary" icon="globe-alt">{{ __('Publish') }}</flux:button>
                    @endif

                    <x-action-message on="quiz-published">{{ __('Published.') }}</x-action-message>
                </div>
            </div>

            @error('publish')
                <flux:text class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror

            @if (in_array($quiz->status, [QuizStatus::Published, QuizStatus::Closed], true))
                <div class="mt-4 flex items-center gap-2" x-data="{ copied: false }">
                    <input
                        type="text"
                        readonly
                        value="{{ route('quiz.play', $quiz->slug) }}"
                        class="w-full flex-1 rounded-lg border-zinc-300 bg-zinc-50 text-sm text-zinc-600 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                        aria-label="{{ __('Public quiz link') }}"
                    />
                    <flux:button
                        variant="filled"
                        icon="clipboard"
                        x-on:click="navigator.clipboard.writeText('{{ route('quiz.play', $quiz->slug) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                    >
                        <span x-show="! copied">{{ __('Copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                    </flux:button>
                </div>
            @endif
        </div>
    @endif

    @if ($canEdit)
        <div class="mt-10 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading>{{ __('Scoring & results') }}</flux:heading>
            <flux:subheading>{{ __('Grade responses and control what respondents see when they finish.') }}</flux:subheading>

            <form wire:submit="saveResults" class="mt-5 max-w-lg space-y-5">
                <div class="flex items-center gap-6">
                    <flux:checkbox wire:model.live="resultScored" label="{{ __('Score this quiz') }}" />
                    @if ($resultScored)
                        <flux:checkbox wire:model="resultShowScore" label="{{ __('Show score to respondents') }}" />
                    @endif
                </div>

                @if ($resultScored)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input
                            wire:model="resultPassPercentage"
                            label="{{ __('Pass mark (%)') }}"
                            type="number"
                            min="0"
                            max="100"
                            placeholder="{{ __('e.g. 60 — empty for none') }}"
                        />

                        <flux:textarea
                            wire:model="resultGrades"
                            label="{{ __('Grade bands (min%:Label)') }}"
                            rows="3"
                            placeholder="80:Excellent&#10;50:Good&#10;0:Keep practicing"
                        />
                    </div>
                @endif

                <flux:textarea
                    wire:model="resultMessage"
                    label="{{ __('Thank-you message') }}"
                    rows="2"
                    placeholder="{{ __('Shown after submitting (optional)') }}"
                />

                <flux:input
                    wire:model="resultRedirect"
                    label="{{ __('Redirect URL') }}"
                    type="url"
                    placeholder="https://example.com/thanks"
                    description="{{ __('Respondents get a button to continue to this link (optional).') }}"
                />

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ __('Save results') }}</flux:button>

                    <x-action-message on="results-saved">{{ __('Saved.') }}</x-action-message>
                </div>
            </form>
        </div>
    @endif

    <div class="mt-10 flex items-center justify-between rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <div>
            <flux:heading>{{ __('Questions') }}</flux:heading>
            <flux:subheading>
                {{ trans_choice('{0}No questions yet — open the builder to add some.|{1}:count question across :pages :pageWord.|[2,*]:count questions across :pages :pageWord.', $quiz->questions()->count(), [
                    'count' => $quiz->questions()->count(),
                    'pages' => max($quiz->pages()->count(), 1),
                    'pageWord' => trans_choice('page|pages', max($quiz->pages()->count(), 1)),
                ]) }}
            </flux:subheading>
        </div>

        @if ($canEdit)
            <flux:button :href="route('quizzes.builder', $quiz)" wire:navigate variant="primary" icon="squares-plus">
                {{ __('Open builder') }}
            </flux:button>
        @endif
    </div>
</section>
