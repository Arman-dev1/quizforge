@php
    $impersonation = app(\App\Services\Platform\Impersonation::class);
    $impersonator = $impersonation->impersonator();
    $viewingAs = auth('web')->user();
@endphp

@if ($impersonator && $viewingAs)
    {{-- Deliberately loud and always on top: acting as someone else must
         never be something you forget you are doing. --}}
    <div class="sticky top-0 z-50 flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-amber-950">
        <span class="flex items-center gap-2">
            <flux:icon.eye class="size-4" />
            {{ __('Viewing as :name (:email)', ['name' => $viewingAs->name, 'email' => $viewingAs->email]) }}
        </span>

        <span class="hidden text-xs font-medium text-amber-900/80 sm:inline">
            {{ __('Signed in by :admin', ['admin' => $impersonator->name]) }}
        </span>

        <form method="POST" action="{{ route('platform.impersonate.stop') }}">
            @csrf
            <button
                type="submit"
                class="rounded-md bg-amber-950/15 px-2.5 py-1 text-xs font-bold text-amber-950 transition hover:bg-amber-950/25"
            >
                {{ __('Stop and return to platform') }}
            </button>
        </form>
    </div>
@endif
