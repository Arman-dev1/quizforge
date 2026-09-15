{{--
  | One content frame for every page: a single max width and one set of
  | gutters, so pages stop each choosing their own and drifting apart.
--}}
<x-layouts.app.sidebar>
    <flux:main class="!p-0">
        <div class="mx-auto w-full max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts.app.sidebar>
