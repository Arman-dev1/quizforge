<?php

use App\Models\WorkspaceIntegration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    #[Url(as: 'q')]
    public string $search = '';

    /** Provider key currently open in the configure modal. */
    public ?string $configuring = null;

    /** Provider key currently open in the disconnect confirm. */
    public ?string $disconnecting = null;

    /** Credential inputs for the configure modal. */
    public array $form = [];

    protected function canManage(): bool
    {
        $workspace = Auth::user()->currentWorkspace;

        return $workspace && (Auth::user()->roleIn($workspace)?->canManageWorkspace() ?? false);
    }

    protected function providerMeta(string $provider): array
    {
        return config("integrations.providers.{$provider}") ?? abort(404);
    }

    public function configure(string $provider): void
    {
        abort_unless($this->canManage(), 403);

        $this->providerMeta($provider);
        $this->configuring = $provider;
        $this->resetErrorBag();

        $existing = WorkspaceIntegration::where('provider', $provider)->first();
        $this->form = $existing?->settings ?? [];

        $this->dispatch('modal-show', name: 'integration-config');
    }

    public function saveConnection(): void
    {
        abort_unless($this->canManage(), 403);

        $provider = $this->configuring;
        $meta = $this->providerMeta($provider);

        $rules = [];
        foreach (array_keys($meta['fields']) as $field) {
            $rules["form.{$field}"] = ['required', 'string', 'max:255'];
        }
        $this->validate($rules);

        WorkspaceIntegration::updateOrCreate(
            ['provider' => $provider],
            ['settings' => $this->form, 'connected_at' => now()],
        );

        $this->configuring = null;
        $this->form = [];
        $this->dispatch('modal-close', name: 'integration-config');
        $this->dispatch('integration-saved');
    }

    public function askDisconnect(string $provider): void
    {
        abort_unless($this->canManage(), 403);

        $this->providerMeta($provider);
        $this->disconnecting = $provider;
        $this->dispatch('modal-show', name: 'integration-disconnect');
    }

    public function disconnect(): void
    {
        abort_unless($this->canManage(), 403);

        if ($this->disconnecting) {
            WorkspaceIntegration::where('provider', $this->disconnecting)->delete();
        }

        $this->disconnecting = null;
        $this->dispatch('modal-close', name: 'integration-disconnect');
    }

    public function with(): array
    {
        $connected = WorkspaceIntegration::pluck('provider')->flip();

        $providers = collect(config('integrations.providers'))
            ->map(fn (array $meta, string $key) => [
                ...$meta,
                'key' => $key,
                'connected' => $connected->has($key),
            ])
            ->when($this->search !== '', fn ($items) => $items->filter(
                fn (array $p) => Str::contains(Str::lower($p['name'].' '.$p['description']), Str::lower($this->search)),
            ))
            ->values();

        return [
            'canManage' => $this->canManage(),
            'category' => config('integrations.categories.email_marketing'),
            'providers' => $providers,
            'popular' => $providers->where('popular', true)->values(),
            'connectedCount' => $connected->count(),
            'configuringMeta' => $this->configuring ? $this->providerMeta($this->configuring) : null,
            'disconnectingMeta' => $this->disconnecting ? $this->providerMeta($this->disconnecting) : null,
        ];
    }
}; ?>

@php($logo = function (array $p) {
    return '<span class="flex size-11 shrink-0 items-center justify-center rounded-xl text-lg font-extrabold" style="background:'.$p['color'].';color:'.$p['ink'].'">'.$p['initial'].'</span>';
})

<section class="w-full">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="tracking-tight">{{ __('Integrations') }}</flux:heading>
            <flux:subheading>{{ __('Connect QuizForge to the tools your team already uses.') }}</flux:subheading>
        </div>

        <div class="w-full max-w-xs">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="{{ __('Search integrations…') }}"
                aria-label="{{ __('Search integrations') }}"
            />
        </div>
    </div>

    {{-- Empty state: no integrations connected yet --}}
    @if ($connectedCount === 0 && $search === '')
        <div class="mt-6 flex flex-col items-center gap-4 rounded-2xl border border-dashed border-zinc-300 bg-white p-8 text-center sm:flex-row sm:text-left dark:border-zinc-700 dark:bg-zinc-900">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400">
                <flux:icon.puzzle-piece class="size-6" />
            </span>
            <div class="flex-1">
                <flux:heading>{{ __('No integrations connected yet') }}</flux:heading>
                <flux:subheading>{{ __('Connect an email marketing tool below and every new lead syncs automatically.') }}</flux:subheading>
            </div>
        </div>
    @endif

    {{-- Popular --}}
    @if ($popular->isNotEmpty())
        <div class="mt-8">
            <div class="mb-3 flex items-center gap-2">
                <flux:icon.sparkles class="size-4 text-teal-600 dark:text-teal-400" />
                <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('Popular integrations') }}</h2>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($popular as $p)
                    @include('livewire.integrations.card', ['p' => $p, 'logo' => $logo, 'canManage' => $canManage])
                @endforeach
            </div>
        </div>
    @endif

    {{-- All in the Email Marketing category --}}
    <div class="mt-8">
        <div class="mb-3 flex items-baseline justify-between gap-2">
            <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">{{ $category['label'] }}</h2>
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $category['description'] }}</span>
        </div>

        @if ($providers->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-2xl border border-zinc-200 bg-white py-14 text-center dark:border-zinc-800 dark:bg-zinc-900">
                <flux:icon.magnifying-glass class="size-8 text-zinc-400" />
                <flux:heading class="mt-3">{{ __('No integrations match your search') }}</flux:heading>
                <flux:subheading>{{ __('Try a different name.') }}</flux:subheading>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($providers as $p)
                    @include('livewire.integrations.card', ['p' => $p, 'logo' => $logo, 'canManage' => $canManage])
                @endforeach
            </div>
        @endif
    </div>

    {{-- Configure / connect modal (always rendered so it survives re-renders) --}}
    <flux:modal name="integration-config" wire:key="integration-config" class="w-full max-w-md">
        @if ($configuringMeta)
            <form wire:submit="saveConnection" class="space-y-5">
                <div class="flex items-center gap-3">
                    {!! $logo($configuringMeta) !!}
                    <div>
                        <flux:heading size="lg">{{ __('Connect :name', ['name' => $configuringMeta['name']]) }}</flux:heading>
                        <flux:subheading>{{ __('Paste your credentials to start syncing leads.') }}</flux:subheading>
                    </div>
                </div>

                @foreach ($configuringMeta['fields'] as $field => $label)
                    <flux:input
                        wire:model="form.{{ $field }}"
                        label="{{ $label }}"
                        type="text"
                        autocomplete="off"
                    />
                @endforeach

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Credentials are encrypted at rest and only used to sync your leads.') }}
                </p>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Save & connect') }}</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    {{-- Disconnect confirm --}}
    <flux:modal name="integration-disconnect" wire:key="integration-disconnect" class="w-full max-w-md">
        @if ($disconnectingMeta)
            <div class="space-y-5">
                <div class="flex gap-3.5">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400">
                        <flux:icon.x-circle class="size-5" />
                    </span>
                    <div>
                        <flux:heading size="lg">{{ __('Disconnect :name?', ['name' => $disconnectingMeta['name']]) }}</flux:heading>
                        <flux:subheading class="mt-1">{{ __('New leads will stop syncing. Your saved credentials are removed.') }}</flux:subheading>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="disconnect">{{ __('Disconnect') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</section>
