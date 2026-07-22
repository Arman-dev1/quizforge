<?php

use App\Models\Quiz;
use App\Services\Integrations\IntegrationException;
use App\Services\Integrations\IntegrationManager;
use Livewire\Volt\Component;

new class extends Component {
    public Quiz $quiz;

    // Setup flow state
    public ?string $setupProvider = null;
    public int $step = 1;              // 1 credentials, 2 resource, 3 mapping
    public array $credentials = [];
    public array $resources = [];
    public ?string $resourceId = null;
    public ?string $resourceName = null;
    public array $mapping = [];
    public string $verifyError = '';

    public ?string $disconnecting = null;

    public function mount(Quiz $quiz): void
    {
        $this->authorize('view', $quiz);

        $this->quiz = $quiz;
    }

    protected function manager(): IntegrationManager
    {
        return app(IntegrationManager::class);
    }

    protected function guardManage(): void
    {
        $this->authorize('update', $this->quiz);
    }

    protected function resetSetup(): void
    {
        $this->reset(['setupProvider', 'step', 'credentials', 'resources', 'resourceId', 'resourceName', 'mapping', 'verifyError']);
        $this->resetErrorBag();
    }

    public function startSetup(string $provider): void
    {
        $this->guardManage();
        abort_unless($this->manager()->exists($provider), 404);

        $this->resetSetup();
        $this->setupProvider = $provider;

        if ($existing = $this->quiz->integrations()->where('provider', $provider)->first()) {
            $this->credentials = $existing->credentials ?? [];
            $this->resourceId = $existing->resource_id;
            $this->resourceName = $existing->resource_name;
            $this->mapping = $existing->mapping ?? [];
        }
    }

    public function cancelSetup(): void
    {
        $this->resetSetup();
    }

    public function verify(): void
    {
        $this->guardManage();
        $meta = $this->manager()->meta($this->setupProvider);

        $rules = [];
        foreach (array_keys($meta['fields']) as $field) {
            $rules["credentials.{$field}"] = ['required', 'string', 'max:255'];
        }
        $this->validate($rules);

        try {
            $driver = $this->manager()->driver($this->setupProvider);
            $driver->verify($this->credentials);
            $this->resources = $driver->resources($this->credentials);
            $this->verifyError = '';

            if (count($this->resources) === 1) {
                $this->resourceId = $this->resources[0]['id'];
            }

            $this->step = 2;
        } catch (IntegrationException $e) {
            $this->verifyError = $e->getMessage();
        }
    }

    public function chooseResource(): void
    {
        $this->guardManage();
        $this->validate(['resourceId' => ['required']], ['resourceId.required' => __('Select a destination to continue.')]);

        $this->resourceName = collect($this->resources)->firstWhere('id', $this->resourceId)['name'] ?? $this->resourceId;

        // Prefill the email mapping with the first email question we can find.
        if (empty($this->mapping['email'])) {
            $emailQuestion = $this->quiz->questions()->where('type', 'email')->first();
            if ($emailQuestion) {
                $this->mapping['email'] = (string) $emailQuestion->id;
            }
        }

        $this->step = 3;
    }

    public function saveMapping(): void
    {
        $this->guardManage();
        $targets = $this->manager()->driver($this->setupProvider)->targetFields();

        $rules = [];
        foreach ($targets as $key => $target) {
            $rules["mapping.{$key}"] = ($target['required'] ?? false) ? ['required'] : ['nullable'];
        }
        $this->validate($rules, ['mapping.email.required' => __('Choose which question holds the email address.')]);

        $this->quiz->integrations()->updateOrCreate(
            ['provider' => $this->setupProvider],
            [
                'credentials' => $this->credentials,
                'resource_id' => $this->resourceId,
                'resource_name' => $this->resourceName,
                'mapping' => array_filter($this->mapping),
                'status' => 'connected',
            ],
        );

        $this->resetSetup();
        $this->dispatch('integration-connected');
    }

    public function askDisconnect(string $provider): void
    {
        $this->guardManage();
        $this->disconnecting = $provider;
        $this->dispatch('modal-show', name: 'integration-disconnect');
    }

    public function disconnect(): void
    {
        $this->guardManage();

        if ($this->disconnecting) {
            $this->quiz->integrations()->where('provider', $this->disconnecting)->delete();
        }

        $this->disconnecting = null;
        $this->dispatch('modal-close', name: 'integration-disconnect');
    }

    public function with(): array
    {
        $connected = $this->quiz->integrations()->get()->keyBy('provider');

        $providers = collect(config('integrations.providers'))
            ->map(fn (array $meta, string $key) => [
                ...$meta,
                'key' => $key,
                'record' => $connected->get($key),
            ])
            ->values();

        $setupMeta = $this->setupProvider ? $this->manager()->meta($this->setupProvider) : null;

        return [
            'canManage' => auth()->user()->can('update', $this->quiz),
            'providers' => $providers,
            'setupMeta' => $setupMeta,
            'setupFields' => $setupMeta ? ($setupMeta['fields'] ?? []) : [],
            'targetFields' => $this->setupProvider ? $this->manager()->driver($this->setupProvider)->targetFields() : [],
            'resourceLabel' => $setupMeta['resource_label'] ?? __('List'),
            'questions' => $this->quiz->questions()->get()->map(fn ($q) => [
                'id' => (string) $q->id,
                'title' => $q->title !== '' ? $q->title : __('Untitled question'),
                'type' => $q->type->value,
            ]),
            'connectedCount' => $connected->count(),
            'disconnectingMeta' => $this->disconnecting ? $this->manager()->meta($this->disconnecting) : null,
        ];
    }
}; ?>

@php($logo = fn (array $p) => '<span class="flex size-11 shrink-0 items-center justify-center rounded-xl text-lg font-extrabold" style="background:'.$p['color'].';color:'.$p['ink'].'">'.$p['initial'].'</span>')

<section class="mx-auto w-full max-w-4xl">
    <div class="min-w-0">
        <a href="{{ route('quizzes.show', $quiz) }}" wire:navigate class="flex w-fit items-center gap-1.5 text-xs font-semibold text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
            <flux:icon.arrow-left class="size-3.5" />
            {{ $quiz->name }}
        </a>
        <flux:heading size="xl" class="mt-1 tracking-tight">{{ __('Integrations') }}</flux:heading>
        <flux:subheading>{{ __('Send this quiz’s leads straight into your email marketing tools.') }}</flux:subheading>
    </div>

    @if ($setupProvider)
        {{-- ─────────────── Setup flow ─────────────── --}}
        <div class="mt-6 overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            {{-- Header + steps --}}
            <div class="border-b border-zinc-200 p-5 dark:border-zinc-800">
                <div class="flex items-center gap-3">
                    {!! $logo($setupMeta) !!}
                    <div class="min-w-0">
                        <p class="font-bold text-zinc-900 dark:text-white">{{ $setupMeta['name'] }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $setupMeta['description'] }}</p>
                    </div>
                    <flux:button variant="subtle" size="sm" wire:click="cancelSetup" class="ml-auto">{{ __('Cancel') }}</flux:button>
                </div>

                <ol class="mt-5 flex flex-wrap items-center gap-x-2 gap-y-2 text-xs font-semibold">
                    @foreach ([1 => __('Connect'), 2 => __('Select :label', ['label' => $resourceLabel]), 3 => __('Map fields')] as $n => $label)
                        <li @class([
                            'flex items-center gap-1.5 rounded-full px-2.5 py-1',
                            'bg-teal-600 text-white' => $step === $n,
                            'bg-teal-50 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300' => $step > $n,
                            'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => $step < $n,
                        ])>
                            <span class="flex size-4 items-center justify-center rounded-full text-[10px] {{ $step > $n ? 'bg-teal-600 text-white' : ($step === $n ? 'bg-white/25' : 'bg-zinc-200 dark:bg-zinc-700') }}">
                                @if ($step > $n)&check;@else{{ $n }}@endif
                            </span>
                            {{ $label }}
                        </li>
                        @if ($n < 3)
                            <li class="text-zinc-300 dark:text-zinc-600" aria-hidden="true">→</li>
                        @endif
                    @endforeach
                </ol>
            </div>

            <div class="p-5">
                {{-- Step 1: credentials + verify --}}
                @if ($step === 1)
                    <div class="max-w-md space-y-4">
                        @foreach ($setupFields as $field => $label)
                            <flux:input wire:model="credentials.{{ $field }}" label="{{ $label }}" type="text" autocomplete="off" />
                        @endforeach

                        @if ($verifyError)
                            <div class="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300">
                                <flux:icon.exclamation-triangle class="mt-0.5 size-4 shrink-0" />
                                <span>{{ $verifyError }}</span>
                            </div>
                        @endif

                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('We make a live request to verify your credentials. They are encrypted at rest.') }}</p>

                        <flux:button variant="primary" icon="bolt" wire:click="verify" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="verify">{{ __('Verify connection') }}</span>
                            <span wire:loading wire:target="verify">{{ __('Verifying…') }}</span>
                        </flux:button>
                    </div>
                @endif

                {{-- Step 2: select resource --}}
                @if ($step === 2)
                    <div class="max-w-md space-y-4">
                        <div class="flex items-center gap-2 rounded-xl border border-teal-200 bg-teal-50 p-3 text-sm font-medium text-teal-700 dark:border-teal-900 dark:bg-teal-950/50 dark:text-teal-300">
                            <flux:icon.check-circle class="size-4" />
                            {{ __('Connection verified.') }}
                        </div>

                        <flux:select wire:model="resourceId" label="{{ __('Destination :label', ['label' => $resourceLabel]) }}">
                            <option value="">{{ __('Choose a :label…', ['label' => strtolower($resourceLabel)]) }}</option>
                            @foreach ($resources as $resource)
                                <option value="{{ $resource['id'] }}">{{ $resource['name'] }}</option>
                            @endforeach
                        </flux:select>
                        @error('resourceId') <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text> @enderror

                        <div class="flex gap-2">
                            <flux:button variant="filled" wire:click="$set('step', 1)">{{ __('Back') }}</flux:button>
                            <flux:button variant="primary" icon-trailing="arrow-right" wire:click="chooseResource">{{ __('Continue') }}</flux:button>
                        </div>
                    </div>
                @endif

                {{-- Step 3: field mapping --}}
                @if ($step === 3)
                    <div class="space-y-4">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Match each :name field to a question. Email is required; the rest are optional.', ['name' => $setupMeta['name']]) }}
                        </p>

                        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800">
                            <div class="grid grid-cols-[1fr_auto_1.4fr] items-center gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/60 dark:text-zinc-400">
                                <span>{{ $setupMeta['name'] }}</span>
                                <span></span>
                                <span>{{ __('Quiz question') }}</span>
                            </div>
                            @foreach ($targetFields as $field => $target)
                                <div class="grid grid-cols-[1fr_auto_1.4fr] items-center gap-3 border-b border-zinc-100 px-4 py-3 last:border-0 dark:border-zinc-800/70" wire:key="map-{{ $field }}">
                                    <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ $target['label'] }}
                                        @if ($target['required'] ?? false)<span class="text-red-500">*</span>@endif
                                    </span>
                                    <flux:icon.arrow-right class="size-4 text-zinc-300 dark:text-zinc-600" />
                                    <flux:select wire:model="mapping.{{ $field }}" aria-label="{{ __('Question for :field', ['field' => $target['label']]) }}">
                                        <option value="">{{ __('— Not mapped —') }}</option>
                                        @foreach ($questions as $q)
                                            <option value="{{ $q['id'] }}">{{ $q['title'] }}</option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            @endforeach
                        </div>
                        @error('mapping.email') <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text> @enderror

                        <div class="flex items-center justify-between">
                            <flux:button variant="filled" wire:click="$set('step', 2)">{{ __('Back') }}</flux:button>
                            <flux:button variant="primary" icon="check" wire:click="saveMapping">{{ __('Save & connect') }}</flux:button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @else
        {{-- ─────────────── Provider grid ─────────────── --}}
        @if ($connectedCount === 0)
            <div class="mt-6 flex flex-col items-center gap-4 rounded-2xl border border-dashed border-zinc-300 bg-white p-8 text-center sm:flex-row sm:text-left dark:border-zinc-700 dark:bg-zinc-900">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400">
                    <flux:icon.bolt class="size-6" />
                </span>
                <div class="flex-1">
                    <flux:heading>{{ __('No integrations connected yet') }}</flux:heading>
                    <flux:subheading>{{ __('Connect a tool below and every completed response syncs automatically.') }}</flux:subheading>
                </div>
            </div>
        @endif

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach ($providers as $p)
                <div class="flex flex-col rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900" wire:key="prov-{{ $p['key'] }}">
                    <div class="flex items-center gap-3">
                        {!! $logo($p) !!}
                        <div class="min-w-0">
                            <p class="truncate font-bold text-zinc-900 dark:text-white">{{ $p['name'] }}</p>
                            @if ($p['record'])
                                <span class="mt-1 inline-flex items-center gap-1.5 rounded-full border border-teal-200 bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-700 dark:border-teal-900 dark:bg-teal-950/60 dark:text-teal-300">
                                    <span class="size-1.5 rounded-full bg-teal-500"></span>{{ __('Connected') }}
                                </span>
                            @else
                                <span class="mt-1 inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-xs font-semibold text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                    <span class="size-1.5 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>{{ __('Not connected') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <p class="mt-3 flex-1 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $p['description'] }}</p>

                    @if ($p['record'])
                        <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                            <flux:icon.arrow-right-circle class="mr-1 inline size-3.5" />{{ $p['record']->resource_name }}
                        </p>
                    @endif

                    <div class="mt-4 flex items-center gap-2">
                        @if ($canManage)
                            @if ($p['record'])
                                <flux:button size="sm" variant="primary" icon="cog-6-tooth" wire:click="startSetup('{{ $p['key'] }}')">{{ __('Configure') }}</flux:button>
                                <flux:button size="sm" variant="filled" wire:click="askDisconnect('{{ $p['key'] }}')">{{ __('Disconnect') }}</flux:button>
                            @else
                                <flux:button size="sm" variant="primary" icon="plus" wire:click="startSetup('{{ $p['key'] }}')" class="w-full justify-center">{{ __('Connect') }}</flux:button>
                            @endif
                        @else
                            <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Only editors can manage integrations.') }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Disconnect confirm (always rendered) --}}
    <flux:modal name="integration-disconnect" wire:key="integration-disconnect" class="w-full max-w-md">
        @if ($disconnectingMeta)
            <div class="space-y-5">
                <div class="flex gap-3.5">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400">
                        <flux:icon.x-circle class="size-5" />
                    </span>
                    <div>
                        <flux:heading size="lg">{{ __('Disconnect :name?', ['name' => $disconnectingMeta['name']]) }}</flux:heading>
                        <flux:subheading class="mt-1">{{ __('New responses will stop syncing and the saved credentials are removed.') }}</flux:subheading>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button variant="danger" wire:click="disconnect">{{ __('Disconnect') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</section>
