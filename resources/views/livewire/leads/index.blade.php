<?php

use App\Models\QuizAnswer;
use App\Models\QuizResponse;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $leads = QuizResponse::query()
            ->whereHas('answers', fn ($query) => $query->where('question_type', 'email'))
            ->when($this->search !== '', fn ($query) => $query->whereHas(
                'answers',
                fn ($answers) => $answers->where('question_type', 'email')->where('value', 'like', '%'.$this->search.'%'),
            ))
            ->with('quiz')
            ->latest('started_at')
            ->paginate(15);

        $contacts = QuizAnswer::query()
            ->whereIn('quiz_response_id', $leads->pluck('id'))
            ->whereIn('question_type', ['email', 'phone'])
            ->orderBy('id')
            ->get()
            ->groupBy('quiz_response_id')
            ->map(fn ($answers) => [
                'email' => $answers->firstWhere('question_type', 'email')?->value,
                'phone' => $answers->firstWhere('question_type', 'phone')?->value,
            ]);

        return [
            'leads' => $leads,
            'contacts' => $contacts,
        ];
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="tracking-tight">{{ __('Leads') }}</flux:heading>
            <flux:subheading>{{ __('Everyone who left an email address — including partial responses.') }}</flux:subheading>
        </div>

        <div class="w-full max-w-xs">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="{{ __('Search by email…') }}"
                aria-label="{{ __('Search leads') }}"
            />
        </div>
    </div>

    @if ($leads->isEmpty())
        <div class="mt-16 flex flex-col items-center justify-center text-center">
            <flux:icon.user-plus class="size-10 text-zinc-400" />
            <flux:heading class="mt-4">
                {{ $search !== '' ? __('No leads match your search') : __('No leads yet') }}
            </flux:heading>
            <flux:subheading class="max-w-sm">
                {{ $search !== '' ? __('Try a different email.') : __('Add an Email question to any quiz — every address lands here automatically, even from unfinished responses.') }}
            </flux:subheading>
        </div>
    @else
        <div class="mt-6 overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800">
            <div class="overflow-x-auto">
                <table class="w-full min-w-160 text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <tr>
                            <th class="px-5 py-3">{{ __('Email') }}</th>
                            <th class="px-5 py-3">{{ __('Phone') }}</th>
                            <th class="px-5 py-3">{{ __('Quiz') }}</th>
                            <th class="px-5 py-3">{{ __('Status') }}</th>
                            <th class="px-5 py-3">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/70">
                        @foreach ($leads as $lead)
                            <tr wire:key="lead-{{ $lead->id }}" class="bg-white dark:bg-zinc-900">
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('quizzes.responses.show', [$lead->quiz_id, $lead->id]) }}" wire:navigate class="font-bold text-zinc-900 hover:underline dark:text-white">
                                        {{ $contacts[$lead->id]['email'] ?? '—' }}
                                    </a>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-zinc-600 dark:text-zinc-300">{{ $contacts[$lead->id]['phone'] ?? '—' }}</td>
                                <td class="px-5 py-3.5 text-zinc-600 dark:text-zinc-300">{{ $lead->quiz?->name }}</td>
                                <td class="px-5 py-3.5">
                                    <span @class([
                                        'rounded-full border px-2.5 py-0.5 text-xs font-semibold',
                                        'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950/60 dark:text-green-300' => $lead->isCompleted(),
                                        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300' => ! $lead->isCompleted(),
                                    ])>
                                        {{ $lead->isCompleted() ? __('Completed') : __('Partial') }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-zinc-600 dark:text-zinc-300">{{ $lead->started_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $leads->links() }}
        </div>
    @endif
</section>
