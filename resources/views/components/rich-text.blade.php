@props([
    'model',
    'label' => null,
    'description' => null,
    'placeholder' => null,
    'rows' => 6,
])

@php
    // A stable id per field so the toolbar buttons and the editable region
    // can be associated for screen readers.
    $editorId = 'rt-'.\Illuminate\Support\Str::slug(str_replace(['.', '[', ']'], '-', $model)).'-'.\Illuminate\Support\Str::random(4);

    $tools = [
        ['cmd' => 'bold', 'icon' => 'bold', 'label' => __('Bold')],
        ['cmd' => 'italic', 'icon' => 'italic', 'label' => __('Italic')],
        ['cmd' => 'underline', 'icon' => 'underline', 'label' => __('Underline')],
        ['divider' => true],
        ['cmd' => 'formatBlock', 'value' => 'h3', 'icon' => 'h3', 'label' => __('Heading')],
        ['cmd' => 'insertUnorderedList', 'icon' => 'list-bullet', 'label' => __('Bulleted list')],
        ['cmd' => 'insertOrderedList', 'icon' => 'numbered-list', 'label' => __('Numbered list')],
        ['divider' => true],
        ['cmd' => 'createLink', 'icon' => 'link', 'label' => __('Insert link')],
        ['cmd' => 'removeFormat', 'icon' => 'x-circle', 'label' => __('Clear formatting')],
    ];
@endphp

<div
    class="flex flex-col gap-2"
    wire:ignore
    x-data="{
        /*
         | The editable region is deliberately un-morphed by Livewire
         | (wire:ignore): re-rendering a contenteditable mid-typing throws the
         | caret to the start. We push to the server on blur instead.
         */
        sync() { $wire.set(@js($model), $refs.editor.innerHTML, false); },
        run(cmd, value = null) {
            $refs.editor.focus();
            if (cmd === 'createLink') {
                const url = window.prompt(@js(__('Link URL')), 'https://');
                if (!url) return;
                document.execCommand('createLink', false, url);
            } else if (cmd === 'formatBlock') {
                document.execCommand('formatBlock', false, value);
            } else {
                document.execCommand(cmd, false, null);
            }
            this.sync();
        },
        /* Paste as plain text so foreign markup never enters the document. */
        pastePlain(event) {
            event.preventDefault();
            const text = (event.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
            this.sync();
        },
    }"
>
    @if ($label)
        <label for="{{ $editorId }}" class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $label }}</label>
    @endif

    <div class="overflow-hidden rounded-lg border border-zinc-300 bg-white focus-within:border-teal-500 focus-within:ring-1 focus-within:ring-teal-500 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-0.5 border-b border-zinc-200 bg-zinc-50 px-1.5 py-1 dark:border-zinc-800 dark:bg-zinc-950/40" role="toolbar" aria-label="{{ __('Formatting') }}">
            @foreach ($tools as $tool)
                @if ($tool['divider'] ?? false)
                    <span class="mx-1 h-4 w-px bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></span>
                @else
                    <button
                        type="button"
                        x-on:click="run(@js($tool['cmd']), @js($tool['value'] ?? null))"
                        class="flex size-7 items-center justify-center rounded text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                        title="{{ $tool['label'] }}"
                        aria-label="{{ $tool['label'] }}"
                    >
                        <flux:icon :icon="$tool['icon']" class="size-4" />
                    </button>
                @endif
            @endforeach
        </div>

        <div
            id="{{ $editorId }}"
            x-ref="editor"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            @if ($label) aria-label="{{ $label }}" @endif
            x-on:blur="sync()"
            x-on:paste="pastePlain($event)"
            data-placeholder="{{ $placeholder ?? __('Write something…') }}"
            class="qf-rich-text w-full overflow-y-auto px-3 py-2.5 text-sm text-zinc-800 focus:outline-none dark:text-zinc-100"
            style="min-height: {{ max(3, (int) $rows) * 1.6 }}rem"
        >{!! $slot !!}</div>
    </div>

    @if ($description)
        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
    @endif

    @error($model)
        <p class="text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
