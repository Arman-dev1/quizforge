<?php

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

        @if ($canEdit)
            <div class="flex items-center gap-2">
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
            </div>
        @endif
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

    <div class="mt-10 rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-600">
        <flux:icon.squares-plus class="mx-auto size-8 text-zinc-400" />
        <flux:heading class="mt-3">{{ __('Question builder coming next') }}</flux:heading>
        <flux:subheading class="mx-auto max-w-sm">
            {{ __('Pages, questions, drag-and-drop, logic, and scoring will live here.') }}
        </flux:subheading>
    </div>
</section>
