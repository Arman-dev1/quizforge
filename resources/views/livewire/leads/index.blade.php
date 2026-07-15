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
            <flux:heading size="xl">{{ __('Leads') }}</flux:heading>
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
        <div class="mt-6 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full min-w-160 text-sm">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">{{ __('Email') }}</th>
                        <th class="px-4 py-3">{{ __('Phone') }}</th>
                        <th class="px-4 py-3">{{ __('Quiz') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($leads as $lead)
                        <tr wire:key="lead-{{ $lead->id }}" class="bg-white dark:bg-zinc-900">
                            <td class="px-4 py-3">
                                <a href="{{ route('quizzes.responses.show', [$lead->quiz_id, $lead->id]) }}" wire:navigate class="font-medium text-zinc-800 hover:underline dark:text-white">
                                    {{ $contacts[$lead->id]['email'] ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $contacts[$lead->id]['phone'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $lead->quiz?->name }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-300' => $lead->isCompleted(),
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300' => ! $lead->isCompleted(),
                                ])>
                                    {{ $lead->isCompleted() ? __('Completed') : __('Partial') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $lead->started_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $leads->links() }}
        </div>
    @endif
</section>
