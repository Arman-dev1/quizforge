<?php

use App\Actions\Quizzes\CreateQuizFromTemplate;
use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Models\Quiz;
use App\Models\QuizTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $type = '';
    public string $tab = 'templates';

    public function mount(): void
    {
        $this->authorize('create', Quiz::class);

        if (! QuizTemplate::availableTo(Auth::user()->currentWorkspace)->exists()) {
            $this->tab = 'scratch';
        }
    }

    public function useTemplate(int $templateId, CreateQuizFromTemplate $action): void
    {
        $this->authorize('create', Quiz::class);

        $template = QuizTemplate::availableTo(Auth::user()->currentWorkspace)->findOrFail($templateId);

        $quiz = $action->handle(Auth::user(), $template);

        $this->redirectRoute('quizzes.builder', $quiz, navigate: true);
    }

    public function deleteTemplate(int $templateId): void
    {
        $this->authorize('create', Quiz::class);

        QuizTemplate::where('workspace_id', Auth::user()->current_workspace_id)
            ->findOrFail($templateId)
            ->delete();
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
        $templates = QuizTemplate::availableTo(Auth::user()->currentWorkspace)
            ->orderBy('name')
            ->get();

        return [
            'groups' => QuizType::grouped(),
            'selected' => QuizType::tryFrom($this->type),
            'templateGroups' => $templates->groupBy('category')->sortKeys(),
            'hasTemplates' => $templates->isNotEmpty(),
        ];
    }
}; ?>

<section class="mx-auto w-full max-w-4xl">
    <div>
        <flux:heading size="xl">{{ __('Create a new quiz') }}</flux:heading>
        <flux:subheading>{{ __('Start from a ready-made template or build from scratch.') }}</flux:subheading>
    </div>

    @if ($hasTemplates)
        <div class="mt-6 flex items-center gap-1 rounded-xl border border-zinc-200 p-1 dark:border-zinc-700" role="tablist">
            @foreach (['templates' => __('Templates'), 'scratch' => __('From scratch')] as $key => $label)
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $key }}')"
                    role="tab"
                    aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    @class([
                        'flex-1 rounded-lg px-4 py-2 text-sm font-medium transition',
                        'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $tab === $key,
                        'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' => $tab !== $key,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endif

    @if ($hasTemplates && $tab === 'templates')
        <div class="mt-8 space-y-8">
            @foreach ($templateGroups as $category => $templates)
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $category }}</p>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($templates as $template)
                            <div class="flex flex-col rounded-xl border border-zinc-200 p-4 transition hover:border-orange-300 dark:border-zinc-700 dark:hover:border-orange-800" wire:key="template-{{ $template->id }}">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $template->name }}</p>
                                    @unless ($template->isGlobal())
                                        <flux:button
                                            variant="subtle"
                                            size="xs"
                                            icon="trash"
                                            wire:click="deleteTemplate({{ $template->id }})"
                                            wire:confirm="{{ __('Delete this template?') }}"
                                            aria-label="{{ __('Delete template') }}"
                                        />
                                    @endunless
                                </div>

                                @if ($template->description)
                                    <p class="mt-1 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $template->description }}</p>
                                @endif

                                <div class="mt-3 flex flex-1 items-end justify-between gap-2">
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $template->type->label() }}
                                        &middot;
                                        {{ trans_choice(':count question|:count questions', $template->questionCount(), ['count' => $template->questionCount()]) }}
                                        @unless ($template->isGlobal())
                                            &middot; {{ __('Yours') }}
                                        @endunless
                                    </span>

                                    <flux:button variant="primary" size="sm" wire:click="useTemplate({{ $template->id }})">
                                        {{ __('Use') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <form wire:submit="create" @class(['mt-8 space-y-8', 'hidden' => $hasTemplates && $tab === 'templates'])>
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
                                    'border-orange-500 bg-orange-50 ring-1 ring-orange-500 dark:bg-orange-950/40' => $selected === $quizType,
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
