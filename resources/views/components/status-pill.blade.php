@props(['status'])

<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold '.$status->badgeClasses()]) }}>
    <span class="size-1.5 rounded-full {{ $status->dotClass() }}"></span>
    {{ $status->label() }}
</span>
