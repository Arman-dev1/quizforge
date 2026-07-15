<?php

use App\Models\Quiz;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.player')] class extends Component {
    public Quiz $quiz;
    public int $step = 0;

    public function mount(Quiz $quiz): void
    {
        $this->authorize('view', $quiz);

        $this->quiz = $quiz;
    }

    public function next(): void
    {
        $this->step++;
    }

    public function previous(): void
    {
        $this->step--;
    }

    public function with(): array
    {
        $pages = $this->quiz->pages()
            ->with(['questions' => fn ($query) => $query->where('is_hidden', false)->with('options')])
            ->get();

        $this->step = max(0, min($this->step, max($pages->count() - 1, 0)));

        return [
            'pages' => $pages,
            'page' => $pages->get($this->step),
            'total' => $pages->count(),
            'progress' => $pages->count() > 0 ? (int) round((($this->step + 1) / $pages->count()) * 100) : 0,
        ];
    }
}; ?>

<div class="flex min-h-svh flex-col">
    {{-- Preview banner --}}
    <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-zinc-900 px-4 py-2 text-center text-xs text-white dark:bg-zinc-800">
        <flux:icon.eye class="size-3.5" />
        {{ __('Preview mode — responses are not saved.') }}
        @can('update', $quiz)
            <a href="{{ route('quizzes.builder', $quiz) }}" class="font-medium underline underline-offset-2">
                {{ __('Back to builder') }}
            </a>
        @endcan
    </div>

    {{-- Progress --}}
    @if ($total > 0)
        <div class="h-1.5 w-full bg-zinc-200 dark:bg-zinc-800">
            <div class="h-full bg-gradient-to-r from-orange-500 to-red-600 transition-all duration-300" style="width: {{ $progress }}%"></div>
        </div>
    @endif

    <main class="mx-auto flex w-full max-w-2xl flex-1 flex-col px-4 py-10 sm:px-6">
        @if (! $page || $page->questions->isEmpty() && $total <= 1 && ! $page->title)
            <div class="m-auto text-center">
                <flux:icon.puzzle-piece class="mx-auto size-10 text-zinc-400" />
                <flux:heading size="lg" class="mt-4">{{ __('Nothing to preview yet') }}</flux:heading>
                <flux:subheading>{{ __('Add questions in the builder and they will appear here.') }}</flux:subheading>
            </div>
        @else
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 dark:border-zinc-800 dark:bg-zinc-900">
                @if ($page->title || $page->description)
                    <div class="mb-8">
                        @if ($page->title)
                            <flux:heading size="xl">{{ $page->title }}</flux:heading>
                        @endif
                        @if ($page->description)
                            <flux:subheading class="mt-1">{{ $page->description }}</flux:subheading>
                        @endif
                    </div>
                @endif

                @if ($page->questions->isEmpty())
                    <flux:text class="text-zinc-500">{{ __('This page has no visible questions yet.') }}</flux:text>
                @else
                    <div class="space-y-8">
                        @foreach ($page->questions as $question)
                            <div wire:key="preview-question-{{ $question->id }}">
                                @include('partials.question-preview', ['question' => $question])
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-6 flex items-center justify-between">
                <div>
                    @if ($step > 0)
                        <flux:button wire:click="previous" variant="filled" icon="arrow-left">
                            {{ __('Previous') }}
                        </flux:button>
                    @endif
                </div>

                <flux:text class="text-xs text-zinc-500">
                    {{ __('Page :current of :total', ['current' => $step + 1, 'total' => max($total, 1)]) }}
                </flux:text>

                <div>
                    @if ($step < $total - 1)
                        <flux:button wire:click="next" variant="primary" icon-trailing="arrow-right">
                            {{ __('Next') }}
                        </flux:button>
                    @else
                        <flux:button variant="primary" disabled title="{{ __('Submitting is available once the quiz is published') }}">
                            {{ __('Submit') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @endif

        <p class="mt-8 text-center text-xs text-zinc-400 dark:text-zinc-500">
            {{ __('Powered by') }} <span class="font-semibold">QuizForge</span>
        </p>
    </main>
</div>
