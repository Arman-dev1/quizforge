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
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->type !== 'all', fn ($query) => $query->where('type', $this->type))
            ->latest('updated_at')
            ->paginate(12);

        return [
            'quizzes' => $quizzes,
            'hasAnyQuizzes' => Quiz::query()->exists(),
            'statuses' => QuizStatus::cases(),
            'types' => QuizType::cases(),
            'canCreate' => Auth::user()->can('create', Quiz::class),
            'canManage' => Auth::user()->roleIn(Auth::user()->currentWorkspace)?->canEditContent() ?? false,
            'isFiltering' => $this->search !== '' || $this->status !== 'all' || $this->type !== 'all',
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Quizzes') }}</flux:heading>
            <flux:subheading>{{ __('Everything in :name', ['name' => auth()->user()->currentWorkspace->name]) }}</flux:subheading>
        </div>

        @if ($canCreate)
            <flux:button :href="route('quizzes.create')" wire:navigate variant="primary" icon="plus">
                {{ __('New quiz') }}
            </flux:button>
        @endif
    </div>

    @if ($hasAnyQuizzes)
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <div class="w-full max-w-xs">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="{{ __('Search quizzes…') }}"
                    aria-label="{{ __('Search quizzes') }}"
                />
            </div>

            <flux:select wire:model.live="status" class="w-36" aria-label="{{ __('Filter by status') }}">
                <option value="all">{{ __('All statuses') }}</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="type" class="w-52" aria-label="{{ __('Filter by type') }}">
                <option value="all">{{ __('All types') }}</option>
                @foreach ($types as $typeOption)
                    <option value="{{ $typeOption->value }}">{{ $typeOption->label() }}</option>
                @endforeach
            </flux:select>
        </div>
    @endif

    @error('quizzes')
        <flux:text class="mt-4 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
    @enderror

    @if ($canManage && ! $canCreate)
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-orange-200 bg-orange-50 p-4 dark:border-orange-900 dark:bg-orange-950/40">
            <p class="text-sm text-orange-800 dark:text-orange-300">
                {{ __('You have reached your plan\'s quiz limit.') }}
            </p>
            <flux:button :href="route('settings.billing')" wire:navigate variant="primary" size="sm">
                {{ __('Upgrade') }}
            </flux:button>
        </div>
    @endif

    @if ($quizzes->isEmpty())
        <div class="mt-16 flex flex-col items-center justify-center text-center">
            @if ($isFiltering)
                <flux:icon.magnifying-glass class="size-10 text-zinc-400" />
                <flux:heading class="mt-4">{{ __('No quizzes match your filters') }}</flux:heading>
                <flux:subheading>{{ __('Try a different search term or clear the filters.') }}</flux:subheading>
            @else
                <flux:icon.puzzle-piece class="size-10 text-zinc-400" />
                <flux:heading class="mt-4">{{ __('Create your first quiz') }}</flux:heading>
                <flux:subheading class="max-w-sm">{{ __('Quizzes, surveys, assessments, and forms all start here. Pick a type and go.') }}</flux:subheading>

                @if ($canCreate)
                    <flux:button :href="route('quizzes.create')" wire:navigate variant="primary" icon="plus" class="mt-6">
                        {{ __('New quiz') }}
                    </flux:button>
                @endif
            @endif
        </div>
    @else
        <ul class="mt-6 divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($quizzes as $quiz)
                <li class="flex items-center gap-4 p-4" wire:key="quiz-{{ $quiz->id }}">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon :icon="$quiz->type->icon()" class="size-5 text-zinc-500 dark:text-zinc-400" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <a href="{{ route('quizzes.show', $quiz) }}" wire:navigate class="block truncate text-sm font-medium text-zinc-800 hover:underline dark:text-white">
                            {{ $quiz->name }}
                        </a>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $quiz->type->label() }}
                            &middot;
                            {{ __('Updated :time', ['time' => $quiz->updated_at->diffForHumans()]) }}
                        </p>
                    </div>

                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $quiz->status->badgeClasses() }}">
                        {{ $quiz->status->label() }}
                    </span>

                    @if ($canManage)
                        <flux:dropdown position="bottom" align="end">
                            <flux:button variant="subtle" size="sm" icon="ellipsis-horizontal" aria-label="{{ __('Actions for :name', ['name' => $quiz->name]) }}" />

                            <flux:menu>
                                <flux:menu.item :href="route('quizzes.show', $quiz)" icon="pencil-square" wire:navigate>
                                    {{ __('Open') }}
                                </flux:menu.item>

                                <flux:menu.item wire:click="duplicate({{ $quiz->id }})" icon="document-duplicate">
                                    {{ __('Duplicate') }}
                                </flux:menu.item>

                                <flux:menu.separator />

                                @if ($quiz->isArchived())
                                    <flux:menu.item wire:click="unarchive({{ $quiz->id }})" icon="arrow-uturn-left">
                                        {{ __('Restore to draft') }}
                                    </flux:menu.item>

                                    <flux:menu.item
                                        wire:click="deleteQuiz({{ $quiz->id }})"
                                        wire:confirm="{{ __('Permanently delete this quiz? This cannot be undone.') }}"
                                        icon="trash"
                                        variant="danger"
                                    >
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                @else
                                    <flux:menu.item
                                        wire:click="archive({{ $quiz->id }})"
                                        wire:confirm="{{ __('Archive this quiz? It will stop accepting responses.') }}"
                                        icon="archive-box"
                                    >
                                        {{ __('Archive') }}
                                    </flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="mt-4">
            {{ $quizzes->links() }}
        </div>
    @endif
</section>
