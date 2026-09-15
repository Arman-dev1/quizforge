<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Appearance')" :subheading="__('How QuizForge looks on this device.')">
        <x-panel :title="__('Theme')" icon="swatch" :description="__('“System” follows your operating system setting.')">
            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
            </flux:radio.group>

            <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('This is stored in your browser, so each device can have its own setting. It does not change how your published quizzes look — those use each quiz’s own Design tab.') }}
            </p>
        </x-panel>
    </x-settings.layout>
</section>
