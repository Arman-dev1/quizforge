@props(['heading' => '', 'subheading' => '', 'wide' => false])

@php
    use App\Enums\WorkspaceRole;

    $user = auth()->user();
    $workspace = $user->currentWorkspace;
    $isOwner = $workspace && $user->roleIn($workspace) === WorkspaceRole::Owner;

    // Grouped, because "my account" and "this workspace" are two different
    // things and mixing them in one flat list is what made this confusing.
    $groups = array_filter([
        [
            'label' => __('Account'),
            'items' => [
                ['route' => 'settings.profile', 'label' => __('Profile'), 'icon' => 'user-circle'],
                ['route' => 'settings.password', 'label' => __('Password'), 'icon' => 'lock-closed'],
                ['route' => 'settings.appearance', 'label' => __('Appearance'), 'icon' => 'swatch'],
                ['route' => 'settings.notifications', 'label' => __('Notifications'), 'icon' => 'bell'],
            ],
        ],
        [
            'label' => __('Workspace'),
            'items' => array_filter([
                ['route' => 'settings.workspace', 'label' => __('General'), 'icon' => 'cog-6-tooth'],
                ['route' => 'settings.members', 'label' => __('Members'), 'icon' => 'users'],
                $isOwner ? ['route' => 'settings.billing', 'label' => __('Billing'), 'icon' => 'credit-card'] : null,
            ]),
        ],
    ]);
@endphp

<div class="flex flex-col gap-8 lg:flex-row lg:gap-10">
    <aside class="w-full shrink-0 lg:w-[210px]">
        <div class="flex flex-col gap-6">
            @foreach ($groups as $group)
                <div>
                    <p class="qf-eyebrow mb-2 px-2">{{ $group['label'] }}</p>
                    <nav class="flex flex-col gap-0.5">
                        @foreach ($group['items'] as $item)
                            @php $current = request()->routeIs($item['route']); @endphp
                            <a
                                href="{{ route($item['route']) }}"
                                wire:navigate
                                @if ($current) aria-current="page" @endif
                                @class([
                                    'flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-semibold transition',
                                    'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' => $current,
                                    'text-zinc-600 hover:bg-zinc-100/70 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/60 dark:hover:text-white' => ! $current,
                                ])
                            >
                                <flux:icon :icon="$item['icon']" @class(['size-4', 'text-teal-600 dark:text-teal-400' => $current]) />
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </nav>
                </div>
            @endforeach
        </div>
    </aside>

    <div class="min-w-0 flex-1">
        <div @class(['flex w-full flex-col gap-5', 'max-w-4xl' => $wide, 'max-w-2xl' => ! $wide])>
            @if ($heading)
                <div>
                    <h2 class="text-lg font-bold tracking-tight text-zinc-900 dark:text-white">{{ $heading }}</h2>
                    @if ($subheading)
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $subheading }}</p>
                    @endif
                </div>
            @endif

            {{ $slot }}
        </div>
    </div>
</div>
