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

<section class="mx-auto w-full max-w-4xl">
    <div>
        <flux:heading size="xl" class="tracking-tight">{{ __('Create a new quiz') }}</flux:heading>
        <flux:subheading>{{ __('Start from a ready-made template or build from scratch.') }}</flux:subheading>
    </div>

    @if ($atLimit)
        <div class="mt-8 rounded-2xl border border-zinc-200 bg-white p-10 text-center dark:border-zinc-800 dark:bg-zinc-900">
            <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400">
                <flux:icon.lock-closed class="size-7" />
            </span>

            <flux:heading size="lg" class="mt-5">{{ __('You have reached your plan limit') }}</flux:heading>
            <flux:subheading class="mx-auto mt-1 max-w-md">
                @if ($quizLimit !== null)
                    {{ __('Your plan includes :limit quizzes and you are using :used. Upgrade for more, or archive a quiz to free up a slot.', ['limit' => $quizLimit, 'used' => $quizUsed]) }}
                @else
                    {{ __('Quiz creation is not available on your current plan.') }}
                @endif
            </flux:subheading>

            @if ($quizLimit !== null)
                <div class="mx-auto mt-5 flex max-w-xs items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-red-500" style="width: {{ min(100, (int) round($quizUsed / max($quizLimit, 1) * 100)) }}%"></div>
                    </div>
                    <span class="font-mono text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ $quizUsed }} / {{ $quizLimit }}</span>
                </div>
            @endif

            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                @if ($canUpgrade)
                    <flux:button :href="route('settings.billing')" wire:navigate variant="primary" icon="sparkles">{{ __('Upgrade plan') }}</flux:button>
                @endif
                <flux:button :href="route('quizzes.index')" wire:navigate variant="filled">{{ __('Manage quizzes') }}</flux:button>
            </div>

            @unless ($canUpgrade)
                <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Ask a workspace owner to upgrade the plan.') }}</p>
            @endunless
        </div>
    @else

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
                            <div class="flex flex-col rounded-2xl border border-zinc-200 bg-white p-4 transition hover:border-teal-300 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-teal-800" wire:key="template-{{ $template->id }}">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $template->name }}</p>
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
                                    'border-teal-500 bg-teal-50 ring-1 ring-teal-500 dark:bg-teal-950/40' => $selected === $quizType,
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
    @endif
</section>
