<?php

use App\Actions\Quizzes\CreateQuizFromTemplate;
use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Enums\WorkspaceRole;
use App\Models\Quiz;
use App\Models\QuizTemplate;
use App\Services\Billing\UsageLimits;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $type = '';
    public string $tab = 'templates';

    public function mount(): void
    {
        // Only content editors may reach the create screen at all; the plan
        // quota is handled gracefully in the view rather than as a raw 403.
        $workspace = Auth::user()->currentWorkspace;

        abort_unless(
            $workspace && (Auth::user()->roleIn($workspace)?->canEditContent() ?? false),
            403,
        );

        if (! QuizTemplate::availableTo($workspace)->exists()) {
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

    public function with(UsageLimits $limits): array
    {
        $workspace = Auth::user()->currentWorkspace;

        $templates = QuizTemplate::availableTo($workspace)
            ->orderBy('name')
            ->get();

        return [
            'groups' => QuizType::grouped(),
            'selected' => QuizType::tryFrom($this->type),
            'templateGroups' => $templates->groupBy('category')->sortKeys(),
            'hasTemplates' => $templates->isNotEmpty(),
            'atLimit' => ! $limits->canCreateQuiz($workspace),
            'quizUsed' => $limits->quizCount($workspace),
            'quizLimit' => $limits->limit($workspace, 'quizzes'),
            'canUpgrade' => Auth::user()->roleIn($workspace) === WorkspaceRole::Owner,
        ];
    }
}; ?>

<section class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <x-page-header
        :title="__('Create a new quiz')"
        :description="__('Start from a ready-made template, or pick a type and build it yourself.')"
        :back="route('quizzes.index')"
        :back-label="__('Quizzes')"
    >
        @if (! $atLimit)
            <x-slot:tabs>
                @if ($hasTemplates)
                    <button type="button" wire:click="$set('tab', 'templates')" @class(['qf-tab', 'qf-tab-active' => $tab === 'templates'])>
                        <flux:icon.rectangle-stack class="size-4" />
                        {{ __('Templates') }}
                    </button>
                @endif
                <button type="button" wire:click="$set('tab', 'scratch')" @class(['qf-tab', 'qf-tab-active' => $tab === 'scratch' || ! $hasTemplates])>
                    <flux:icon.sparkles class="size-4" />
                    {{ __('From scratch') }}
                </button>
            </x-slot:tabs>
        @endif
    </x-page-header>

    @if ($atLimit)
        <div class="qf-surface">
            <x-empty-state
                icon="lock-closed"
                :title="__('You have reached your plan limit')"
                :description="$quizLimit !== null
                    ? __('Your plan includes :limit quizzes and you are using :used. Upgrade for more, or archive a quiz to free up a slot.', ['limit' => $quizLimit, 'used' => $quizUsed])
                    : __('Quiz creation is not available on your current plan.')"
            >
                @if ($canUpgrade)
                    <flux:button :href="route('settings.billing')" wire:navigate variant="primary" icon="sparkles">{{ __('Upgrade plan') }}</flux:button>
                @endif
                <flux:button :href="route('quizzes.index')" wire:navigate variant="filled">{{ __('Manage quizzes') }}</flux:button>
            </x-empty-state>

            @if ($quizLimit !== null)
                <div class="mx-auto -mt-8 mb-10 flex max-w-xs items-center gap-3">
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-red-500" style="width: {{ min(100, (int) round($quizUsed / max($quizLimit, 1) * 100)) }}%"></div>
                    </div>
                    <span class="qf-num text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ $quizUsed }} / {{ $quizLimit }}</span>
                </div>
            @endif

            @unless ($canUpgrade)
                <p class="pb-8 text-center text-xs text-zinc-500 dark:text-zinc-400">{{ __('Ask a workspace owner to upgrade the plan.') }}</p>
            @endunless
        </div>
    @else
        @if ($hasTemplates && $tab === 'templates')
            <div class="flex flex-col gap-8">
                @foreach ($templateGroups as $category => $templates)
                    <div>
                        <p class="qf-eyebrow mb-3">{{ $category }}</p>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($templates as $template)
                                <div class="qf-surface-interactive flex flex-col p-4" wire:key="template-{{ $template->id }}">
                                    <div class="flex items-start gap-3">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400">
                                            <flux:icon :icon="$template->type->icon()" class="size-4.5" />
                                        </span>

                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-bold text-zinc-900 dark:text-white">{{ $template->name }}</p>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $template->type->label() }}
                                                &middot;
                                                {{ trans_choice(':count question|:count questions', $template->questionCount(), ['count' => $template->questionCount()]) }}
                                            </p>
                                        </div>

                                        @unless ($template->isGlobal())
                                            <x-confirm
                                                :name="'del-template-'.$template->id"
                                                action="deleteTemplate({{ $template->id }})"
                                                :title="__('Delete this template?')"
                                                :description="__('This removes the saved template. Quizzes already created from it are not affected.')"
                                                :confirm="__('Delete template')"
                                                icon="trash"
                                            >
                                                <x-slot:trigger>
                                                    <flux:button variant="subtle" size="xs" icon="trash" aria-label="{{ __('Delete template') }}" />
                                                </x-slot:trigger>
                                            </x-confirm>
                                        @endunless
                                    </div>

                                    @if ($template->description)
                                        <p class="mt-3 line-clamp-2 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $template->description }}</p>
                                    @endif

                                    <div class="mt-4 flex flex-1 items-end justify-between gap-2">
                                        @unless ($template->isGlobal())
                                            <span class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                                {{ __('Yours') }}
                                            </span>
                                        @else
                                            <span></span>
                                        @endunless

                                        <flux:button variant="primary" size="sm" wire:click="useTemplate({{ $template->id }})">
                                            {{ __('Use template') }}
                                        </flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <form wire:submit="create" @class(['flex flex-col gap-6', 'hidden' => $hasTemplates && $tab === 'templates'])>
            {{-- Name first: you know what you're making before you know
                 which of fourteen types it is. --}}
            <x-panel :title="__('Name it')" icon="pencil-square">
                <flux:input
                    wire:model="name"
                    :label="__('Quiz name')"
                    type="text"
                    :placeholder="__('e.g. Customer Satisfaction Q3')"
                    :description="__('You can rename it later — this also sets the public link.')"
                />
            </x-panel>

            <div class="flex flex-col gap-6">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-base font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('Pick a type') }}</h2>
                    @if ($selected)
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Selected: :type', ['type' => $selected->label()]) }}</span>
                    @endif
                </div>

                @foreach ($groups as $category => $types)
                    <div>
                        <p class="qf-eyebrow mb-3">{{ $category }}</p>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($types as $quizType)
                                <button
                                    type="button"
                                    wire:click="selectType('{{ $quizType->value }}')"
                                    wire:key="type-{{ $quizType->value }}"
                                    @class([
                                        'flex items-start gap-3 rounded-xl border p-3.5 text-left transition',
                                        'border-teal-600 bg-teal-50/70 ring-1 ring-teal-600 dark:border-teal-500 dark:bg-teal-950/40 dark:ring-teal-500' => $selected === $quizType,
                                        'border-zinc-200 bg-white hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700 dark:hover:bg-zinc-800/60' => $selected !== $quizType,
                                    ])
                                    aria-pressed="{{ $selected === $quizType ? 'true' : 'false' }}"
                                >
                                    <span @class([
                                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                        'bg-teal-600 text-white' => $selected === $quizType,
                                        'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => $selected !== $quizType,
                                    ])>
                                        <flux:icon :icon="$quizType->icon()" class="size-4.5" />
                                    </span>

                                    <span class="min-w-0">
                                        <span class="block text-sm font-bold text-zinc-900 dark:text-white">{{ $quizType->label() }}</span>
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

            {{-- Sticky so "Create" is reachable without scrolling back past
                 fourteen type cards. --}}
            <div class="sticky bottom-0 -mx-4 mt-2 flex items-center justify-end gap-3 border-t border-zinc-200 bg-zinc-50/90 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6 dark:border-zinc-800 dark:bg-zinc-950/90">
                <flux:button :href="route('quizzes.index')" wire:navigate variant="filled">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="plus">{{ __('Create quiz') }}</flux:button>
            </div>
        </form>
    @endif
</section>
