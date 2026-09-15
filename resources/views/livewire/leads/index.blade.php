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
                fn ($answers) => $answers->where('question_type', 'email')
                    ->where('value', 'like', '%'.addcslashes($this->search, '%_\\').'%'),
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

        // Unfiltered totals for the summary tiles — a search shouldn't make
        // it look like leads disappeared.
        $allLeads = QuizResponse::query()
            ->whereHas('answers', fn ($query) => $query->where('question_type', 'email'));

        return [
            'leads' => $leads,
            'contacts' => $contacts,
            'totalLeads' => (clone $allLeads)->count(),
            'completedLeads' => (clone $allLeads)->where('status', QuizResponse::STATUS_COMPLETED)->count(),
            'leadsThisMonth' => (clone $allLeads)->where('started_at', '>=', now()->startOfMonth())->count(),
        ];
    }
}; ?>

<section class="flex w-full flex-col gap-6">
    <x-page-header
        :title="__('Leads')"
        :description="__('Everyone who left an email address — including people who never finished.')"
    >
        @if ($totalLeads > 0)
            <div class="w-full sm:w-72">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    :placeholder="__('Search by email…')"
                    :aria-label="__('Search leads')"
                    clearable
                />
            </div>
        @endif
    </x-page-header>

    @if ($totalLeads > 0)
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat :label="__('Total leads')" :value="number_format($totalLeads)" icon="user-plus" />
            <x-stat :label="__('From completed')" :value="number_format($completedLeads)" icon="check-circle" :hint="__('Finished the whole quiz')" />
            <x-stat
                :label="__('From partials')"
                :value="number_format($totalLeads - $completedLeads)"
                icon="clock"
                :hint="__('Left an email but dropped off')"
            />
            <x-stat :label="__('This month')" :value="number_format($leadsThisMonth)" icon="calendar" :hint="now()->format('F')" />
        </div>
    @endif

    @if ($leads->isEmpty())
        <div class="qf-surface">
            @if ($search !== '')
                <x-empty-state
                    icon="magnifying-glass"
                    :title="__('No leads match that search')"
                    :description="__('Try a different email address or clear the search.')"
                />
            @else
                <x-empty-state
                    icon="user-plus"
                    :title="__('No leads yet')"
                    :description="__('Add an Email question to any quiz — every address lands here automatically, even from unfinished responses.')"
                >
                    <flux:button :href="route('quizzes.index')" wire:navigate variant="primary" icon="puzzle-piece">
                        {{ __('Go to your quizzes') }}
                    </flux:button>
                </x-empty-state>
            @endif
        </div>
    @else
        <div class="qf-surface overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-160 text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50/60 text-left dark:border-zinc-800 dark:bg-zinc-950/30">
                        <tr>
                            <th class="qf-eyebrow px-5 py-3 font-semibold">{{ __('Email') }}</th>
                            <th class="qf-eyebrow px-5 py-3 font-semibold">{{ __('Phone') }}</th>
                            <th class="qf-eyebrow px-5 py-3 font-semibold">{{ __('Quiz') }}</th>
                            <th class="qf-eyebrow px-5 py-3 font-semibold">{{ __('Status') }}</th>
                            <th class="qf-eyebrow px-5 py-3 font-semibold">{{ __('Captured') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($leads as $lead)
                            <tr wire:key="lead-{{ $lead->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('quizzes.responses.show', [$lead->quiz_id, $lead->id]) }}" wire:navigate class="font-bold text-zinc-900 hover:text-teal-700 dark:text-white dark:hover:text-teal-400">
                                        {{ $contacts[$lead->id]['email'] ?? '—' }}
                                    </a>
                                </td>

                                <td class="qf-num px-5 py-3.5 text-zinc-600 dark:text-zinc-300">
                                    {{ $contacts[$lead->id]['phone'] ?? '—' }}
                                </td>

                                <td class="px-5 py-3.5">
                                    @if ($lead->quiz)
                                        <a href="{{ route('quizzes.show', $lead->quiz_id) }}" wire:navigate class="text-zinc-600 hover:text-teal-700 dark:text-zinc-300 dark:hover:text-teal-400">
                                            {{ $lead->quiz->name }}
                                        </a>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3.5">
                                    <span @class([
                                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold',
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' => $lead->isCompleted(),
                                        'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400' => ! $lead->isCompleted(),
                                    ])>
                                        <span @class([
                                            'size-1.5 rounded-full',
                                            'bg-emerald-500' => $lead->isCompleted(),
                                            'bg-amber-500' => ! $lead->isCompleted(),
                                        ])></span>
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

        @if ($leads->hasPages())
            <div>{{ $leads->links() }}</div>
        @endif
    @endif
</section>
