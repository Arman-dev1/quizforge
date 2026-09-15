<?php

use App\Actions\Quizzes\PublishQuiz;
use App\Actions\Quizzes\SaveQuizAsTemplate;
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

    // Read-only summary of the result configuration. Editing lives on the
    // Results tab (quizzes.results); this page only reports what is set.
    public bool $resultScored = false;

    public string $resultPassPercentage = '';

    public string $resultMode = 'simple';

    public int $resultOutcomeCount = 0;

    public function loadResultSettings(): void
    {
        $settings = $this->quiz->settings ?? [];
        $results = $settings['results'] ?? [];

        $this->resultScored = (bool) ($settings['scored'] ?? false);
        $this->resultPassPercentage = (string) ($results['pass_percentage'] ?? '');
        $this->resultMode = in_array($results['mode'] ?? null, ['score', 'category'], true) ? $results['mode'] : 'simple';
        $this->resultOutcomeCount = count(array_filter($results['outcomes'] ?? [], 'is_array'));
    }

    public function saveAsTemplate(SaveQuizAsTemplate $action): void
    {
        $this->authorize('update', $this->quiz);

        $action->handle($this->quiz, Auth::user());

        $this->dispatch('template-saved');
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
        $responseCount = $this->quiz->responses()->count();
        $completedCount = $this->quiz->responses()->where('status', \App\Models\QuizResponse::STATUS_COMPLETED)->count();

        return [
            'canEdit' => Auth::user()->can('update', $this->quiz),
            'questionCount' => $this->quiz->questions()->count(),
            'pageCount' => max($this->quiz->pages()->count(), 1),
            'responseCount' => $responseCount,
            'completedCount' => $completedCount,
            'completionRate' => $responseCount > 0 ? (int) round($completedCount / $responseCount * 100) : null,
            'publicUrl' => route('quiz.play', $this->quiz->slug),
            'latestVersion' => $this->quiz->latestVersion(),
            'resultModeLabel' => match ($this->resultMode) {
                'score' => __('Score-based results'),
                'category' => __('Category-based results'),
                default => __('One thank-you message'),
            },
            'resultModeIcon' => match ($this->resultMode) {
                'score' => 'chart-bar',
                'category' => 'tag',
                default => 'chat-bubble-bottom-center-text',
            },
            'resultModeSummary' => match ($this->resultMode) {
                'score' => trans_choice(
                    '{0}No score bands set up yet|{1}:count band, matched on the score|[2,*]:count bands, matched on the score',
                    $this->resultOutcomeCount,
                    ['count' => $this->resultOutcomeCount],
                ),
                'category' => trans_choice(
                    '{0}No categories set up yet|{1}:count result, matched on the category chosen most|[2,*]:count results, matched on the category chosen most',
                    $this->resultOutcomeCount,
                    ['count' => $this->resultOutcomeCount],
                ),
                default => __('Everyone who submits sees the same message.'),
            },
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
            <span>{{ $quiz->type->label() }}</span>
            @if ($latestVersion)
                <span class="text-zinc-300 dark:text-zinc-700">&middot;</span>
                <span>{{ __('Version :v', ['v' => $latestVersion->version]) }}</span>
            @endif
            <span class="text-zinc-300 dark:text-zinc-700">&middot;</span>
            <span>{{ __('Created :time', ['time' => $quiz->created_at->diffForHumans()]) }}</span>
        </x-slot:meta>

        {{-- Two visible actions and a menu. Everything else that used to sit
             in this row is now a tab or a menu item. --}}
        <flux:button href="{{ route('quizzes.preview', $quiz) }}" target="_blank" variant="filled" icon="eye">
            {{ __('Preview') }}
        </flux:button>

        @if ($canEdit)
            @if ($quiz->status === QuizStatus::Draft)
                <flux:button wire:click="publish" variant="primary" icon="globe-alt">{{ __('Publish') }}</flux:button>
            @elseif ($quiz->status === QuizStatus::Closed)
                <flux:button wire:click="reopen" variant="primary" icon="lock-open">{{ __('Reopen') }}</flux:button>
            @else
                <flux:button :href="route('quizzes.builder', $quiz)" wire:navigate variant="primary" icon="squares-plus">
                    {{ __('Edit questions') }}
                </flux:button>
            @endif

            <flux:dropdown position="bottom" align="end">
                <flux:button variant="filled" icon="ellipsis-horizontal" aria-label="{{ __('More actions') }}" />

                <flux:menu>
                    <flux:menu.item wire:click="duplicate" icon="document-duplicate">{{ __('Duplicate quiz') }}</flux:menu.item>
                    <flux:menu.item wire:click="saveAsTemplate" icon="bookmark">{{ __('Save as template') }}</flux:menu.item>

                    @if ($quiz->status === QuizStatus::Published)
                        <flux:menu.separator />
                        <flux:menu.item icon="lock-closed" x-on:click="$dispatch('modal-show', { name: 'quiz-close' })">
                            {{ __('Close to new responses') }}
                        </flux:menu.item>
                    @endif

                    <flux:menu.separator />

                    @if ($quiz->isArchived())
                        <flux:menu.item wire:click="unarchive" icon="arrow-uturn-left">{{ __('Restore to draft') }}</flux:menu.item>
                        <flux:menu.item icon="trash" variant="danger" x-on:click="$dispatch('modal-show', { name: 'quiz-delete' })">
                            {{ __('Delete quiz') }}
                        </flux:menu.item>
                    @else
                        <flux:menu.item icon="archive-box" x-on:click="$dispatch('modal-show', { name: 'quiz-archive' })">
                            {{ __('Archive quiz') }}
                        </flux:menu.item>
                    @endif
                </flux:menu>
            </flux:dropdown>
        @endif

        <x-slot:tabs>
            <x-quiz-nav :quiz="$quiz" :response-count="$responseCount" />
        </x-slot:tabs>
    </x-page-header>

    {{-- Confirmation modals: always rendered (never behind a status @if) so the
         menu items above, which appear after a state change, always have a live
         modal to open. --}}
    @if ($canEdit)
        <x-confirm
            name="quiz-archive"
            action="archive"
            :title="__('Archive this quiz?')"
            :description="__('It will stop accepting responses and move to your archive. You can restore it later.')"
            :confirm="__('Archive')"
            tone="primary"
            icon="archive-box"
        />
        <x-confirm
            name="quiz-delete"
            action="deleteQuiz"
            :title="__('Delete this quiz?')"
            :description="__('This permanently deletes the quiz and all of its responses. This cannot be undone.')"
            :confirm="__('Delete quiz')"
            icon="trash"
        />
        <x-confirm
            name="quiz-close"
            action="closeQuiz"
            :title="__('Close this quiz?')"
            :description="__('Respondents will see a notice that it is no longer accepting responses. You can reopen it anytime.')"
            :confirm="__('Close quiz')"
            tone="primary"
            icon="lock-closed"
        />
    @endif

    @error('actions')
        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
    @enderror

    @error('publish')
        <div class="flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 dark:border-red-900/60 dark:bg-red-950/30">
            <flux:icon.exclamation-circle class="mt-0.5 size-4.5 shrink-0 text-red-600 dark:text-red-400" />
            <p class="text-sm font-medium text-red-800 dark:text-red-300">{{ $message }}</p>
        </div>
    @enderror

    {{-- Sharing leads the page. Once a quiz is live, getting the link is the
         thing people come here to do; it used to be the third card down. --}}
    @if (in_array($quiz->status, [QuizStatus::Published, QuizStatus::Closed], true))
        <div class="qf-surface overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-4 p-5">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400">
                        <flux:icon.share class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Public link') }}</h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $quiz->status->description() }}</p>
                    </div>
                </div>

                <div class="flex flex-1 flex-wrap items-center justify-end gap-2 sm:flex-nowrap" x-data="{ copied: false }">
                    <input
                        type="text"
                        readonly
                        value="{{ $publicUrl }}"
                        x-on:focus="$event.target.select()"
                        class="qf-well min-w-0 flex-1 px-3 py-2 font-mono text-xs text-zinc-600 sm:min-w-[280px] dark:text-zinc-300"
                        aria-label="{{ __('Public quiz link') }}"
                    />
                    <flux:button
                        variant="primary"
                        x-on:click="navigator.clipboard.writeText(@js($publicUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                    >
                        <span x-show="!copied" class="flex items-center gap-1.5"><flux:icon.clipboard class="size-4" />{{ __('Copy') }}</span>
                        <span x-show="copied" x-cloak class="flex items-center gap-1.5"><flux:icon.check class="size-4" />{{ __('Copied') }}</span>
                    </flux:button>
                    <flux:button href="{{ $publicUrl }}" target="_blank" variant="filled" icon="arrow-top-right-on-square" aria-label="{{ __('Open public link') }}" />
                </div>
            </div>

            @if ($canEdit && $quiz->status === QuizStatus::Published)
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 bg-zinc-50/60 px-5 py-3 dark:border-zinc-800 dark:bg-zinc-950/30">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Edits stay private until you republish. Live respondents keep seeing version :v.', ['v' => $latestVersion?->version]) }}
                    </p>
                    <div class="flex items-center gap-2">
                        <x-action-message on="quiz-published">{{ __('Published.') }}</x-action-message>
                        <flux:button wire:click="publish" size="sm" variant="filled" icon="arrow-path">{{ __('Republish changes') }}</flux:button>
                    </div>
                </div>
            @endif
        </div>
    @elseif ($canEdit)
        {{-- Draft: say what publishing does and make it one click. --}}
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-dashed border-teal-300 bg-teal-50/50 p-5 dark:border-teal-900 dark:bg-teal-950/20">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-700 dark:bg-teal-950/60 dark:text-teal-400">
                    <flux:icon.globe-alt class="size-5" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Not published yet') }}</h2>
                    <p class="text-xs text-zinc-600 dark:text-zinc-400">
                        {{ $questionCount === 0
                            ? __('Add at least one question, then publish to get a shareable link.')
                            : __('Publish to get a public link and start collecting responses.') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <x-action-message on="quiz-published">{{ __('Published.') }}</x-action-message>
                @if ($questionCount === 0)
                    <flux:button :href="route('quizzes.builder', $quiz)" wire:navigate variant="primary" icon="squares-plus">
                        {{ __('Add questions') }}
                    </flux:button>
                @else
                    <flux:button wire:click="publish" variant="primary" icon="globe-alt">{{ __('Publish quiz') }}</flux:button>
                @endif
            </div>
        </div>
    @endif

    {{-- Shape of the quiz and how it is performing, in one row. --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat
            :label="__('Questions')"
            :value="$questionCount"
            icon="queue-list"
            :hint="trans_choice('across :count page|across :count pages', $pageCount, ['count' => $pageCount])"
            :href="$canEdit ? route('quizzes.builder', $quiz) : null"
        />
        <x-stat
            :label="__('Responses')"
            :value="number_format($responseCount)"
            icon="inbox"
            :hint="__(':n completed', ['n' => number_format($completedCount)])"
            :href="route('quizzes.responses', $quiz)"
        />
        <x-stat
            :label="__('Completion rate')"
            :value="$completionRate === null ? '—' : $completionRate . '%'"
            icon="check-circle"
            :hint="$completionRate === null ? __('No responses yet') : __('of everyone who started')"
            :href="route('quizzes.analytics', $quiz)"
        />
        <x-stat
            :label="__('Scoring')"
            :value="$resultScored ? __('On') : __('Off')"
            icon="academic-cap"
            :hint="$resultScored ? __('Points and grades applied') : __('Responses are not graded')"
        />
    </div>

    @if ($canEdit)
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2 xl:items-start">
            <x-panel :title="__('Details')" icon="document-text">
                <form wire:submit="updateDetails" class="flex flex-col gap-5">
                    <flux:input wire:model="name" :label="__('Name')" type="text" required />

                    <flux:input
                        value="{{ $quiz->slug }}"
                        :label="__('Public link')"
                        type="text"
                        disabled
                        :description="__('Your quiz is available at /quiz/:slug once published.', ['slug' => $quiz->slug])"
                    />

                    <flux:textarea
                        wire:model="description"
                        :label="__('Description')"
                        rows="3"
                        :placeholder="__('Internal notes about this quiz (optional)')"
                    />

                    <div class="flex items-center gap-4">
                        <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                        <x-action-message on="quiz-updated">{{ __('Saved.') }}</x-action-message>
                    </div>
                </form>
            </x-panel>

            {{-- Result screens have their own tab now; this points at it
                 rather than duplicating the controls here. --}}
            <x-panel :title="__('Result screens')" icon="sparkles" :description="__('What respondents see after they submit')">
                <div class="flex flex-col gap-4">
                    <div class="qf-well flex items-start gap-3 p-4">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400">
                            <flux:icon :icon="$resultModeIcon" class="size-4.5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-zinc-900 dark:text-white">{{ $resultModeLabel }}</p>
                            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $resultModeSummary }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <flux:button :href="route('quizzes.results', $quiz)" wire:navigate variant="filled" icon="sparkles">
                            {{ __('Edit result screens') }}
                        </flux:button>

                        @if ($resultScored)
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $resultPassPercentage !== ''
                                    ? __('Scored · pass mark :n%', ['n' => $resultPassPercentage])
                                    : __('Scored · no pass mark set') }}
                            </span>
                        @else
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Responses are not graded') }}</span>
                        @endif
                    </div>
                </div>
            </x-panel>
        </div>
    @endif

    <x-action-message on="template-saved">{{ __('Template saved.') }}</x-action-message>
</section>
