@props(['quiz', 'responseCount' => null])

@php
    // One row of tabs replaces what used to be nine same-weight buttons.
    // Order follows the actual job: set it up, make it look right, wire it
    // up, then read what came back.
    $tabs = [
        ['route' => 'quizzes.show', 'label' => __('Overview'), 'icon' => 'squares-2x2', 'match' => 'quizzes.show'],
        ['route' => 'quizzes.builder', 'label' => __('Build'), 'icon' => 'squares-plus', 'match' => 'quizzes.builder'],
        ['route' => 'quizzes.design', 'label' => __('Design'), 'icon' => 'swatch', 'match' => 'quizzes.design'],
        ['route' => 'quizzes.results', 'label' => __('Results'), 'icon' => 'sparkles', 'match' => 'quizzes.results'],
        ['route' => 'quizzes.integrations', 'label' => __('Connect'), 'icon' => 'bolt', 'match' => 'quizzes.integrations'],
        ['route' => 'quizzes.responses', 'label' => __('Responses'), 'icon' => 'inbox', 'match' => 'quizzes.responses*', 'count' => $responseCount],
        ['route' => 'quizzes.analytics', 'label' => __('Analytics'), 'icon' => 'chart-bar', 'match' => 'quizzes.analytics'],
    ];
@endphp

@foreach ($tabs as $tab)
    @php $current = request()->routeIs($tab['match']); @endphp
    <a
        href="{{ route($tab['route'], $quiz) }}"
        wire:navigate
        @class(['qf-tab', 'qf-tab-active' => $current])
        @if ($current) aria-current="page" @endif
    >
        <flux:icon :icon="$tab['icon']" class="size-4" />
        {{ $tab['label'] }}
        @if (($tab['count'] ?? 0) > 0)
            <span class="qf-num rounded-full bg-zinc-100 px-1.5 text-[11px] font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                {{ $tab['count'] }}
            </span>
        @endif
    </a>
@endforeach
