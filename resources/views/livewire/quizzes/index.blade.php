<?php

use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $type = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'type']);
        $this->resetPage();
    }

    public function duplicate(int $quizId): void
    {
        $quiz = Quiz::findOrFail($quizId);

        $this->authorize('create', Quiz::class);
        $this->authorize('view', $quiz);

        $copy = $quiz->duplicate(Auth::user());

        $this->redirectRoute('quizzes.show', $copy, navigate: true);
    }

    public function archive(int $quizId): void
    {
        $quiz = Quiz::findOrFail($quizId);

        $this->authorize('update', $quiz);

        $quiz->archive();
    }

    public function unarchive(int $quizId): void
    {
        $quiz = Quiz::findOrFail($quizId);

        $this->authorize('update', $quiz);

        $quiz->unarchive();
    }

    public function deleteQuiz(int $quizId): void
    {
        $quiz = Quiz::findOrFail($quizId);

        $this->authorize('delete', $quiz);

        if (! $quiz->isArchived()) {
            $this->addError('quizzes', __('Archive a quiz before deleting it.'));

            return;
        }

        $quiz->delete();
    }

    public function with(): array
    {
        $quizzes = Quiz::query()
            ->withCount('responses')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($this->search, '%_\\').'%'))
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->type !== 'all', fn ($query) => $query->where('type', $this->type))
            ->latest('updated_at')
            ->paginate(12);

        // Counts per status drive the filter tabs — a filter that shows you
        // how much is behind it before you click.
        $byStatus = Quiz::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'quizzes' => $quizzes,
            'byStatus' => $byStatus,
            'totalCount' => $byStatus->sum(),
            'hasAnyQuizzes' => $byStatus->sum() > 0,
            'statuses' => QuizStatus::cases(),
            'types' => QuizType::cases(),
            'canCreate' => Auth::user()->can('create', Quiz::class),
            'canManage' => Auth::user()->roleIn(Auth::user()->currentWorkspace)?->canEditContent() ?? false,
            'isFiltering' => $this->search !== '' || $this->status !== 'all' || $this->type !== 'all',
        ];
    }
}; ?>

<section class="flex w-full flex-col gap-6">
    <x-page-header
        :title="__('Quizzes')"
        :description="__('Everything in :name', ['name' => auth()->user()->currentWorkspace->name])"
    >
        @if ($canCreate)
            <flux:button :href="route('quizzes.create')" wire:navigate variant="primary" icon="plus">
                {{ __('New quiz') }}
            </flux:button>
        @endif

        <x-slot:tabs>
            @if ($hasAnyQuizzes)
                <button type="button" wire:click="setStatus('all')" @class(['qf-tab', 'qf-tab-active' => $status === 'all'])>
                    {{ __('All') }}
                    <span class="qf-num rounded-full bg-zinc-100 px-1.5 text-[11px] font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $totalCount }}</span>
                </button>

                @foreach ($statuses as $statusOption)
                    @php $count = $byStatus[$statusOption->value] ?? 0; @endphp
                    <button
                        type="button"
                        wire:click="setStatus('{{ $statusOption->value }}')"
                        @class(['qf-tab', 'qf-tab-active' => $status === $statusOption->value])
                    >
                        {{ $statusOption->label() }}
                        @if ($count > 0)
                            <span class="qf-num rounded-full bg-zinc-100 px-1.5 text-[11px] font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $count }}</span>
                        @endif
                    </button>
                @endforeach
            @endif
        </x-slot:tabs>
    </x-page-header>

    @if ($canManage && ! $canCreate)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900/60 dark:bg-amber-950/30">
            <div class="flex items-center gap-2.5">
                <flux:icon.exclamation-triangle class="size-4.5 shrink-0 text-amber-600 dark:text-amber-400" />
                <p class="text-sm font-medium text-amber-900 dark:text-amber-200">
                    {{ __('You have reached your plan\'s quiz limit. Upgrade to create more.') }}
                </p>
            </div>
            <flux:button :href="route('settings.billing')" wire:navigate variant="primary" size="sm">
                {{ __('Upgrade') }}
            </flux:button>
        </div>
    @endif

    @error('quizzes')
        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
    @enderror

    @if ($hasAnyQuizzes)
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:max-w-xs">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="{{ __('Search quizzes…') }}"
                    aria-label="{{ __('Search quizzes') }}"
                    clearable
                />
            </div>

            <flux:select wire:model.live="type" class="w-full sm:w-52" aria-label="{{ __('Filter by type') }}">
                <option value="all">{{ __('All types') }}</option>
                @foreach ($types as $typeOption)
                    <option value="{{ $typeOption->value }}">{{ $typeOption->label() }}</option>
                @endforeach
            </flux:select>

            @if ($isFiltering)
                <flux:button wire:click="clearFilters" variant="subtle" size="sm" icon="x-mark">
                    {{ __('Clear') }}
                </flux:button>
            @endif

            <span class="ml-auto hidden text-sm text-zinc-500 sm:block dark:text-zinc-400">
                {{ trans_choice('{0}No quizzes|{1}1 quiz|[2,*]:count quizzes', $quizzes->total(), ['count' => number_format($quizzes->total())]) }}
            </span>
        </div>
    @endif

    @if ($quizzes->isEmpty())
        <div class="qf-surface">
            @if ($isFiltering)
                <x-empty-state
                    icon="magnifying-glass"
                    :title="__('Nothing matches those filters')"
                    :description="__('Try a different search term, or clear the filters to see everything.')"
                >
                    <flux:button wire:click="clearFilters" variant="filled" icon="x-mark">{{ __('Clear filters') }}</flux:button>
                </x-empty-state>
            @else
                <x-empty-state
                    icon="puzzle-piece"
                    :title="__('Create your first quiz')"
                    :description="__('Quizzes, surveys, assessments and lead forms all start here. Pick a type, add questions, publish.')"
                >
                    @if ($canCreate)
                        <flux:button :href="route('quizzes.create')" wire:navigate variant="primary" icon="plus">
                            {{ __('New quiz') }}
                        </flux:button>
                    @endif
                </x-empty-state>
            @endif
        </div>
    @else
        <div class="qf-surface overflow-hidden">
            {{-- Column headings so the numbers on the right have names. --}}
            <div class="hidden items-center gap-4 border-b border-zinc-200 bg-zinc-50/60 px-5 py-2.5 md:flex dark:border-zinc-800 dark:bg-zinc-950/30">
                <span class="qf-eyebrow flex-1">{{ __('Quiz') }}</span>
                <span class="qf-eyebrow w-24 text-right">{{ __('Responses') }}</span>
                <span class="qf-eyebrow w-28">{{ __('Status') }}</span>
                <span class="w-8"></span>
            </div>

            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($quizzes as $quiz)
                    <li class="group flex items-center gap-4 px-5 py-3.5 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40" wire:key="quiz-{{ $quiz->id }}">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400">
                            <flux:icon :icon="$quiz->type->icon()" class="size-5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <a href="{{ route('quizzes.show', $quiz) }}" wire:navigate class="block truncate text-sm font-bold text-zinc-900 hover:text-teal-700 dark:text-white dark:hover:text-teal-400">
                                {{ $quiz->name }}
                            </a>
                            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $quiz->type->label() }}
                                &middot;
                                {{ __('Updated :time', ['time' => $quiz->updated_at->diffForHumans()]) }}
                            </p>
                        </div>

                        {{-- Sharing is the job right after publishing, so the link
                             is one click from the list, not buried on the detail page. --}}
                        @if ($quiz->status === App\Enums\QuizStatus::Published)
                            <div
                                class="hidden lg:block"
                                x-data="{ copied: false }"
                                x-on:click="navigator.clipboard.writeText(@js(route('quiz.play', $quiz->slug))); copied = true; setTimeout(() => copied = false, 1600)"
                            >
                                <flux:button size="sm" variant="subtle" class="opacity-0 transition group-hover:opacity-100 focus-visible:opacity-100">
                                    <span x-show="!copied" class="flex items-center gap-1.5">
                                        <flux:icon.link class="size-4" />{{ __('Copy link') }}
                                    </span>
                                    <span x-show="copied" x-cloak class="flex items-center gap-1.5 text-teal-600 dark:text-teal-400">
                                        <flux:icon.check class="size-4" />{{ __('Copied') }}
                                    </span>
                                </flux:button>
                            </div>
                        @endif

                        <div class="w-24 text-right max-md:hidden">
                            @if ($quiz->responses_count > 0)
                                <a href="{{ route('quizzes.responses', $quiz) }}" wire:navigate class="qf-num text-sm font-bold text-zinc-900 hover:text-teal-700 dark:text-white dark:hover:text-teal-400">
                                    {{ number_format($quiz->responses_count) }}
                                </a>
                            @else
                                <span class="qf-num text-sm font-medium text-zinc-300 dark:text-zinc-600">0</span>
                            @endif
                        </div>

                        <div class="md:w-28">
                            <x-status-pill :status="$quiz->status" />
                        </div>

                        @if ($canManage)
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="subtle" size="sm" icon="ellipsis-horizontal" aria-label="{{ __('Actions for :name', ['name' => $quiz->name]) }}" />

                                <flux:menu>
                                    <flux:menu.item :href="route('quizzes.show', $quiz)" icon="squares-2x2" wire:navigate>
                                        {{ __('Overview') }}
                                    </flux:menu.item>

                                    <flux:menu.item :href="route('quizzes.builder', $quiz)" icon="squares-plus" wire:navigate>
                                        {{ __('Edit questions') }}
                                    </flux:menu.item>

                                    <flux:menu.item wire:click="duplicate({{ $quiz->id }})" icon="document-duplicate">
                                        {{ __('Duplicate') }}
                                    </flux:menu.item>

                                    <flux:menu.separator />

                                    @if ($quiz->isArchived())
                                        <flux:menu.item wire:click="unarchive({{ $quiz->id }})" icon="arrow-uturn-left">
                                            {{ __('Restore to draft') }}
                                        </flux:menu.item>

                                        <flux:menu.item icon="trash" variant="danger" x-on:click="$dispatch('modal-show', { name: 'del-quiz-{{ $quiz->id }}' })">
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    @else
                                        <flux:menu.item icon="archive-box" x-on:click="$dispatch('modal-show', { name: 'archive-quiz-{{ $quiz->id }}' })">
                                            {{ __('Archive') }}
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>

                            {{-- Both modals are always rendered (outside the dropdown, and never
                                 behind an @if) so a Livewire re-render never has to create a fresh
                                 Flux modal — one created mid-morph does not re-initialise. --}}
                            <x-confirm
                                :name="'archive-quiz-'.$quiz->id"
                                action="archive({{ $quiz->id }})"
                                :title="__('Archive this quiz?')"
                                :description="__('It will stop accepting responses and move to your archive. You can restore it later.')"
                                :confirm="__('Archive')"
                                tone="primary"
                                icon="archive-box"
                            />
                            <x-confirm
                                :name="'del-quiz-'.$quiz->id"
                                action="deleteQuiz({{ $quiz->id }})"
                                :title="__('Delete this quiz?')"
                                :description="__('This permanently deletes the quiz and all of its responses. This cannot be undone.')"
                                :confirm="__('Delete quiz')"
                                icon="trash"
                            />
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($quizzes->hasPages())
            <div>{{ $quizzes->links() }}</div>
        @endif
    @endif
</section>
