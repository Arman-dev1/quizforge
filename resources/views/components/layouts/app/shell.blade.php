{{--
  | Full-bleed variant of the app frame: same sidebar, workspace switcher and
  | account menu as every other page, but the content area gets the whole
  | viewport with no max width and no gutters.
  |
  | For full-screen tools (the builder) that manage their own internal panes
  | and need to fill the height.
--}}
<x-layouts.app.sidebar>
    <flux:main class="!p-0">
        <div class="h-full min-h-svh w-full lg:h-svh lg:overflow-hidden">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts.app.sidebar>
