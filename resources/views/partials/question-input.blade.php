@php
    use App\Enums\QuestionType;

    $type = QuestionType::from($question['type']);
    $settings = $question['settings'] ?? [];
    $qid = $question['id'];
    $model = "answers.{$qid}";

    // Styling lives in app.css under #qf-player, driven by the quiz's own
    // --qf-* design variables, so the Design tab reaches the real controls.
    $hasError = $errors->has("answers.{$qid}") || $errors->has("answers.{$qid}.*");
@endphp

<fieldset class="qf-question" @if ($hasError) data-invalid="true" @endif>
    <legend class="block text-base font-semibold" style="color: var(--qf-text)">
        {{ $question['title'] }}
        @if ($question['is_required'])
            <span aria-hidden="true" style="color:#dc2626">*</span>
            <span class="sr-only">{{ __('(required)') }}</span>
        @endif
    </legend>

    @if ($question['description'])
        <p class="mt-1 text-sm" style="color: var(--qf-muted)">{{ $question['description'] }}</p>
    @endif

    <div class="mt-3.5">
        @if ($type === QuestionType::SingleChoice)
            <div class="flex flex-col gap-2" role="radiogroup" aria-label="{{ $question['title'] }}">
                @foreach ($question['options'] as $option)
                    <label class="qf-choice">
                        <input type="radio" wire:model="{{ $model }}" value="{{ $option['id'] }}" />
                        <span class="text-sm">{{ $option['label'] }}</span>
                    </label>
                @endforeach
            </div>

        @elseif ($type === QuestionType::MultipleChoice)
            {{-- The bound property is seeded to [] by the component, which is
                 what lets Livewire gather these into an array. --}}
            <div class="flex flex-col gap-2">
                @foreach ($question['options'] as $option)
                    <label class="qf-choice">
                        <input type="checkbox" wire:model="{{ $model }}" value="{{ $option['id'] }}" />
                        <span class="text-sm">{{ $option['label'] }}</span>
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs" style="color: var(--qf-muted)">{{ __('Choose as many as apply.') }}</p>

        @elseif ($type === QuestionType::Dropdown)
            <select wire:model="{{ $model }}" class="qf-field" aria-label="{{ $question['title'] }}">
                <option value="">{{ $question['placeholder'] ?: __('Select an option…') }}</option>
                @foreach ($question['options'] as $option)
                    <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>

        @elseif ($type === QuestionType::ImageChoice)
            @php($disk = \Illuminate\Support\Facades\Storage::disk('public'))
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($question['options'] as $option)
                    @php($image = $option['image'] ?? null)
                    <label class="qf-choice !flex-col !items-stretch !gap-2 !p-2 text-center">
                        <input type="radio" wire:model="{{ $model }}" value="{{ $option['id'] }}" class="sr-only" />
                        @if ($image)
                            <img src="{{ $disk->url($image) }}" alt="" class="aspect-video w-full rounded-md object-cover" />
                        @else
                            <span class="flex aspect-video items-center justify-center rounded-md" style="background: color-mix(in srgb, var(--qf-text) 8%, transparent)">
                                <flux:icon.photo class="size-6" style="color: var(--qf-muted)" />
                            </span>
                        @endif
                        <span class="block truncate text-xs font-medium">{{ $option['label'] }}</span>
                    </label>
                @endforeach
            </div>

        @elseif ($type === QuestionType::YesNo)
            <div class="grid grid-cols-2 gap-3" role="radiogroup" aria-label="{{ $question['title'] }}">
                @foreach (['yes' => __('Yes'), 'no' => __('No')] as $value => $label)
                    <label class="qf-choice justify-center">
                        <input type="radio" wire:model="{{ $model }}" value="{{ $value }}" class="sr-only" />
                        <span class="text-sm font-medium">{{ $label }}</span>
                    </label>
                @endforeach
            </div>

        @elseif ($type === QuestionType::Ranking)
            {{-- Inline @php(...) only: this file already has one block form
                 at the top, and Blade mispairs a second one. --}}
            @php($order = collect($answers[$qid] ?? array_column($question['options'], 'id')))
            @php($optionsById = collect($question['options'])->keyBy('id'))
            <ol x-sortable data-sort-method="sortRanking" data-sort-target="{{ $qid }}" class="flex flex-col gap-2">
                @foreach ($order as $optionId)
                    @continue(! $optionsById->has((int) $optionId))
                    <li
                        wire:key="rank-{{ $qid }}-{{ $optionId }}"
                        data-sort-id="{{ $optionId }}"
                        class="flex items-center gap-3 rounded-lg border p-3"
                        style="border-color: var(--qf-card-border); border-radius: var(--qf-radius)"
                    >
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                            style="background: color-mix(in srgb, var(--qf-primary) 15%, transparent); color: var(--qf-text)"
                        >{{ $loop->iteration }}</span>
                        <span class="text-sm">{{ $optionsById[(int) $optionId]['label'] }}</span>
                        <span data-sort-handle class="ml-auto cursor-grab" style="color: var(--qf-muted)" aria-hidden="true">
                            <flux:icon.bars-2 class="size-4" />
                        </span>
                    </li>
                @endforeach
            </ol>
            <p class="mt-2 text-xs" style="color: var(--qf-muted)">{{ __('Drag to put in order — 1 is your top choice.') }}</p>

        @elseif ($type === QuestionType::LongText)
            @php($maxLength = $settings['max_length'] ?? null)
            <textarea
                wire:model="{{ $model }}"
                rows="4"
                class="qf-field"
                placeholder="{{ $question['placeholder'] ?: __('Type your answer…') }}"
                aria-label="{{ $question['title'] }}"
                @if ($maxLength) maxlength="{{ $maxLength }}" @endif
            ></textarea>

        @elseif (in_array($type, [QuestionType::ShortText, QuestionType::Email, QuestionType::Phone, QuestionType::Website], true))
            @php($inputType = match ($type) {
                QuestionType::Email => 'email',
                QuestionType::Phone => 'tel',
                QuestionType::Website => 'url',
                default => 'text',
            })
            @php($autocomplete = match ($type) {
                QuestionType::Email => 'email',
                QuestionType::Phone => 'tel',
                QuestionType::Website => 'url',
                default => 'off',
            })
            @php($placeholder = $question['placeholder'] ?: match ($type) {
                QuestionType::Email => 'you@example.com',
                QuestionType::Phone => '+1 555 000 0000',
                QuestionType::Website => 'https://example.com',
                default => __('Type your answer…'),
            })
            <input
                type="{{ $inputType }}"
                wire:model="{{ $model }}"
                class="qf-field"
                placeholder="{{ $placeholder }}"
                autocomplete="{{ $autocomplete }}"
                aria-label="{{ $question['title'] }}"
                @if ($settings['max_length'] ?? false) maxlength="{{ $settings['max_length'] }}" @endif
            />

        @elseif ($type === QuestionType::Number)
            <input
                type="number"
                wire:model="{{ $model }}"
                class="qf-field max-w-48"
                placeholder="{{ $question['placeholder'] ?: '0' }}"
                aria-label="{{ $question['title'] }}"
                @if (($settings['min'] ?? null) !== null) min="{{ $settings['min'] }}" @endif
                @if (($settings['max'] ?? null) !== null) max="{{ $settings['max'] }}" @endif
            />

        @elseif ($type === QuestionType::Date)
            <input type="date" wire:model="{{ $model }}" class="qf-field max-w-56" aria-label="{{ $question['title'] }}" />

        @elseif ($type === QuestionType::Time)
            <input type="time" wire:model="{{ $model }}" class="qf-field max-w-48" aria-label="{{ $question['title'] }}" />

        @elseif ($type === QuestionType::Address)
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <input type="text" wire:model="{{ $model }}.street" class="qf-field sm:col-span-2" placeholder="{{ __('Street address') }}" autocomplete="street-address" aria-label="{{ __('Street address') }}" />
                <input type="text" wire:model="{{ $model }}.city" class="qf-field" placeholder="{{ __('City') }}" autocomplete="address-level2" aria-label="{{ __('City') }}" />
                <input type="text" wire:model="{{ $model }}.state" class="qf-field" placeholder="{{ __('State / Province') }}" autocomplete="address-level1" aria-label="{{ __('State or province') }}" />
                <input type="text" wire:model="{{ $model }}.postal_code" class="qf-field" placeholder="{{ __('Postal code') }}" autocomplete="postal-code" aria-label="{{ __('Postal code') }}" />
                <input type="text" wire:model="{{ $model }}.country" class="qf-field" placeholder="{{ __('Country') }}" autocomplete="country-name" aria-label="{{ __('Country') }}" />
            </div>

        @elseif ($type === QuestionType::Rating)
            @php($max = max(1, (int) ($settings['max'] ?? 5)))
            @php($current = (int) ($answers[$qid] ?? 0))
            <div class="flex items-center gap-1" role="radiogroup" aria-label="{{ $question['title'] }}">
                @for ($i = 1; $i <= $max; $i++)
                    <button
                        type="button"
                        wire:click="$set('{{ $model }}', {{ $i }})"
                        class="rounded p-0.5 transition"
                        aria-label="{{ trans_choice(':count star|:count stars', $i, ['count' => $i]) }}"
                        aria-pressed="{{ $current === $i ? 'true' : 'false' }}"
                    >
                        <flux:icon.star @class([
                            'size-7 transition',
                            'fill-amber-400 text-amber-400' => $current >= $i,
                        ]) @style([
                            'color: var(--qf-muted); opacity:.45' => $current < $i,
                        ]) />
                    </button>
                @endfor

                @if ($current > 0)
                    <button
                        type="button"
                        wire:click="$set('{{ $model }}', null)"
                        class="ml-2 text-xs underline underline-offset-2"
                        style="color: var(--qf-muted)"
                    >{{ __('Clear') }}</button>
                @endif
            </div>

        @elseif (in_array($type, [QuestionType::OpinionScale, QuestionType::LinearScale, QuestionType::Nps], true))
            @php($min = $type === QuestionType::Nps ? 0 : (int) ($settings['min'] ?? 1))
            @php($max = $type === QuestionType::Nps ? 10 : (int) ($settings['max'] ?? 5))
            @php($current = ($answers[$qid] ?? null) === null || $answers[$qid] === '' ? null : (int) $answers[$qid])
            <div>
                <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ $question['title'] }}">
                    @for ($i = $min; $i <= $max; $i++)
                        <button
                            type="button"
                            wire:click="$set('{{ $model }}', {{ $i }})"
                            class="qf-scale-btn"
                            aria-pressed="{{ $current === $i ? 'true' : 'false' }}"
                        >{{ $i }}</button>
                    @endfor
                </div>

                @php($minLabel = ($settings['min_label'] ?? '') !== '' ? $settings['min_label'] : ($type === QuestionType::Nps ? __('Not at all likely') : ''))
                @php($maxLabel = ($settings['max_label'] ?? '') !== '' ? $settings['max_label'] : ($type === QuestionType::Nps ? __('Extremely likely') : ''))
                @if ($minLabel !== '' || $maxLabel !== '')
                    <div class="mt-2 flex justify-between gap-4 text-xs" style="color: var(--qf-muted)">
                        <span>{{ $minLabel }}</span>
                        <span>{{ $maxLabel }}</span>
                    </div>
                @endif
            </div>

        @elseif ($type === QuestionType::Matrix)
            @php($rows = $settings['rows'] ?? [])
            @php($columns = $settings['columns'] ?? [])
            <div class="overflow-x-auto">
                <table class="qf-matrix w-full min-w-96 text-sm">
                    <thead>
                        <tr>
                            <th class="text-left"><span class="sr-only">{{ __('Row') }}</span></th>
                            @foreach ($columns as $column)
                                <th scope="col">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $rowIndex => $row)
                            <tr>
                                <th scope="row" class="text-left font-normal" style="color: var(--qf-text)">{{ $row }}</th>
                                @foreach ($columns as $columnIndex => $column)
                                    <td class="text-center">
                                        <input
                                            type="radio"
                                            wire:model="{{ $model }}.{{ $rowIndex }}"
                                            value="{{ $columnIndex }}"
                                            aria-label="{{ $row }}: {{ $column }}"
                                        />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @elseif (in_array($type, [QuestionType::FileUpload, QuestionType::Signature], true))
            <div
                class="flex flex-col items-center justify-center rounded-lg border border-dashed p-8 text-center"
                style="border-color: var(--qf-card-border); border-radius: var(--qf-radius)"
            >
                <flux:icon :icon="$type === QuestionType::FileUpload ? 'arrow-up-tray' : 'pencil-square'" class="size-6" style="color: var(--qf-muted)" />
                <p class="mt-2 text-sm" style="color: var(--qf-muted)">
                    {{ $type === QuestionType::FileUpload ? __('File uploads are coming soon.') : __('Signatures are coming soon.') }}
                </p>
            </div>
        @endif
    </div>

    @if ($question['help_text'])
        <p class="mt-2 text-xs" style="color: var(--qf-muted)">{{ $question['help_text'] }}</p>
    @endif

    @if ($hasError)
        <p class="mt-2 text-sm font-medium" style="color:#dc2626" role="alert">
            {{ $errors->first("answers.{$qid}") ?: $errors->first("answers.{$qid}.*") }}
        </p>
    @endif
</fieldset>
