<?php

use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $type = '';

    public function mount(): void
    {
        $this->authorize('create', Quiz::class);
    }

    public function selectType(string $type): void
    {
        if (QuizType::tryFrom($type)) {
            $this->type = $type;
        }
    }

    public function create(): void
    {
        $this->authorize('create', Quiz::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'type' => ['required', Rule::enum(QuizType::class)],
        ]);

        $type = QuizType::from($validated['type']);

        $quiz = Quiz::create([
            'created_by' => Auth::id(),
            'name' => $validated['name'],
            'slug' => Quiz::generateSlug($validated['name']),
            'type' => $type,
            'status' => QuizStatus::Draft,
            'settings' => $type->defaultSettings(),
        ]);

        $this->redirectRoute('quizzes.show', $quiz, navigate: true);
    }

    public function with(): array
    {
        return [
            'groups' => QuizType::grouped(),
            'selected' => QuizType::tryFrom($this->type),
        ];
    }
}; ?>

<section class="mx-auto w-full max-w-4xl">
    <div>
        <flux:heading size="xl">{{ __('Create a new quiz') }}</flux:heading>
        <flux:subheading>{{ __('Pick a type to get the right defaults — you can change everything later.') }}</flux:subheading>
    </div>

    <form wire:submit="create" class="mt-8 space-y-8">
        <div class="space-y-6">
            @foreach ($groups as $category => $types)
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $category }}</p>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($types as $quizType)
                            <button
                                type="button"
                                wire:click="selectType('{{ $quizType->value }}')"
                                wire:key="type-{{ $quizType->value }}"
                                @class([
                                    'flex items-start gap-3 rounded-xl border p-3 text-left transition',
                                    'border-violet-500 bg-violet-50 ring-1 ring-violet-500 dark:bg-violet-950/40' => $selected === $quizType,
                                    'border-zinc-200 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-600 dark:hover:bg-zinc-800/60' => $selected !== $quizType,
                                ])
                                aria-pressed="{{ $selected === $quizType ? 'true' : 'false' }}"
                            >
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700">
                                    <flux:icon :icon="$quizType->icon()" class="size-5 text-zinc-600 dark:text-zinc-300" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-zinc-800 dark:text-white">{{ $quizType->label() }}</span>
                                    <span class="mt-0.5 block text-xs leading-snug text-zinc-500 dark:text-zinc-400">{{ $quizType->description() }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @error('type')
                <flux:text class="text-red-600 dark:text-red-400">{{ __('Choose a quiz type to continue.') }}</flux:text>
            @enderror
        </div>

        <div class="flex items-end gap-3 border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <div class="flex-1">
                <flux:input
                    wire:model="name"
                    label="{{ __('Quiz name') }}"
                    type="text"
                    placeholder="{{ __('e.g. Customer Satisfaction Q3') }}"
                />
            </div>

            <flux:button :href="route('quizzes.index')" wire:navigate variant="filled">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" type="submit">{{ __('Create quiz') }}</flux:button>
        </div>
    </form>
</section>
