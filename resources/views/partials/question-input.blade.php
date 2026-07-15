@php
    use App\Enums\QuestionType;

    $type = QuestionType::from($question['type']);
    $settings = $question['settings'] ?? [];
    $qid = $question['id'];
    $model = "answers.{$qid}";
    $inputClasses = 'w-full rounded-lg border-zinc-300 bg-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white';
    $choiceCardClasses = 'flex cursor-pointer items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-orange-300 hover:bg-orange-50/40 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50 dark:border-zinc-700 dark:hover:border-orange-800 dark:hover:bg-orange-950/20 dark:has-[:checked]:border-orange-500 dark:has-[:checked]:bg-orange-950/40';
    $scaleButtonClasses = 'flex size-10 items-center justify-center rounded-lg border text-sm font-medium transition';
@endphp

<fieldset>
    <legend class="block text-base font-medium text-zinc-900 dark:text-white">
        {{ $question['title'] }}
        @if ($question['is_required'])
            <span class="text-red-500" aria-hidden="true">*</span>
        @endif
    </legend>

    @if ($question['description'])
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $question['description'] }}</p>
    @endif

    <div class="mt-3">
        @if ($type === QuestionType::SingleChoice)
            <div class="space-y-2">
                @foreach ($question['options'] as $option)
                    <label class="{{ $choiceCardClasses }}">
                        <input type="radio" wire:model="{{ $model }}" value="{{ $option['id'] }}" class="size-4 border-zinc-300 text-orange-600 focus:ring-orange-500 dark:border-zinc-600 dark:bg-zinc-800" />
                        <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ $option['label'] }}</span>
                    </label>
                @endforeach
            </div>
        @elseif ($type === QuestionType::MultipleChoice)
            <div class="space-y-2">
                @foreach ($question['options'] as $option)
                    <label class="{{ $choiceCardClasses }}">
                        <input type="checkbox" wire:model="{{ $model }}" value="{{ $option['id'] }}" class="size-4 rounded border-zinc-300 text-orange-600 focus:ring-orange-500 dark:border-zinc-600 dark:bg-zinc-800" />
                        <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ $option['label'] }}</span>
                    </label>
                @endforeach
            </div>
        @elseif ($type === QuestionType::Dropdown)
            <select wire:model="{{ $model }}" class="{{ $inputClasses }}">
                <option value="">{{ $question['placeholder'] ?: __('Select an option…') }}</option>
                @foreach ($question['options'] as $option)
                    <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        @elseif ($type === QuestionType::ImageChoice)
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($question['options'] as $option)
                    <label class="cursor-pointer rounded-xl border border-zinc-200 p-2 text-center transition hover:border-orange-300 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50 dark:border-zinc-700 dark:has-[:checked]:bg-orange-950/40">
                        <input type="radio" wire:model="{{ $model }}" value="{{ $option['id'] }}" class="sr-only" />
                        <span class="flex aspect-video items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                            <flux:icon.photo class="size-6 text-zinc-400" />
                        </span>
                        <span class="mt-2 block truncate text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $option['label'] }}</span>
                    </label>
                @endforeach
            </div>
        @elseif ($type === QuestionType::YesNo)
            <div class="grid grid-cols-2 gap-3">
                @foreach (['yes' => __('Yes'), 'no' => __('No')] as $value => $label)
                    <label class="{{ $choiceCardClasses }} justify-center">
                        <input type="radio" wire:model="{{ $model }}" value="{{ $value }}" class="sr-only" />
                        <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        @elseif ($type === QuestionType::Ranking)
            @php
                $order = collect($answers[$qid] ?? array_column($question['options'], 'id'));
                $optionsById = collect($question['options'])->keyBy('id');
            @endphp
            <ol x-sortable data-sort-method="sortRanking" data-sort-target="{{ $qid }}" class="space-y-2">
                @foreach ($order as $optionId)
                    @continue(! $optionsById->has((int) $optionId))
                    <li
                        wire:key="rank-{{ $qid }}-{{ $optionId }}"
                        data-sort-id="{{ $optionId }}"
                        class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <span class="flex size-6 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $loop->iteration }}</span>
                        <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ $optionsById[(int) $optionId]['label'] }}</span>
                        <span data-sort-handle class="ml-auto text-zinc-300 dark:text-zinc-600" aria-hidden="true">
                            <flux:icon.bars-2 class="size-4" />
                        </span>
                    </li>
                @endforeach
            </ol>
            <p class="mt-2 text-xs text-zinc-400">{{ __('Drag to put in order — 1 is your top choice.') }}</p>
        @elseif ($type === QuestionType::LongText)
            <textarea wire:model="{{ $model }}" rows="4" placeholder="{{ $question['placeholder'] ?: __('Type your answer…') }}" @if($settings['max_length'] ?? false) maxlength="{{ $settings['max_length'] }}" @endif class="{{ $inputClasses }}"></textarea>
        @elseif (in_array($type, [QuestionType::ShortText, QuestionType::Email, QuestionType::Phone, QuestionType::Website], true))
            @php($inputType = match ($type) {
                QuestionType::Email => 'email',
                QuestionType::Phone => 'tel',
                QuestionType::Website => 'url',
                default => 'text',
            })
            <input type="{{ $inputType }}" wire:model="{{ $model }}" placeholder="{{ $question['placeholder'] ?: __('Type your answer…') }}" @if($settings['max_length'] ?? false) maxlength="{{ $settings['max_length'] }}" @endif class="{{ $inputClasses }}" />
        @elseif ($type === QuestionType::Number)
            <input type="number" wire:model="{{ $model }}" placeholder="{{ $question['placeholder'] ?: '0' }}" class="{{ $inputClasses }} max-w-48" />
        @elseif ($type === QuestionType::Date)
            <input type="date" wire:model="{{ $model }}" class="{{ $inputClasses }} max-w-48" />
        @elseif ($type === QuestionType::Time)
            <input type="time" wire:model="{{ $model }}" class="{{ $inputClasses }} max-w-48" />
        @elseif ($type === QuestionType::Address)
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <input type="text" wire:model="{{ $model }}.street" placeholder="{{ __('Street address') }}" class="{{ $inputClasses }} sm:col-span-2" />
                <input type="text" wire:model="{{ $model }}.city" placeholder="{{ __('City') }}" class="{{ $inputClasses }}" />
                <input type="text" wire:model="{{ $model }}.state" placeholder="{{ __('State / Province') }}" class="{{ $inputClasses }}" />
                <input type="text" wire:model="{{ $model }}.postal_code" placeholder="{{ __('Postal code') }}" class="{{ $inputClasses }}" />
                <input type="text" wire:model="{{ $model }}.country" placeholder="{{ __('Country') }}" class="{{ $inputClasses }}" />
            </div>
        @elseif ($type === QuestionType::Rating)
            @php($max = (int) ($settings['max'] ?? 5))
            @php($current = (int) ($answers[$qid] ?? 0))
            <div class="flex items-center gap-1">
                @for ($i = 1; $i <= $max; $i++)
                    <button
                        type="button"
                        wire:click="$set('{{ $model }}', {{ $i }})"
                        aria-label="{{ __(':count stars', ['count' => $i]) }}"
                        aria-pressed="{{ $current === $i ? 'true' : 'false' }}"
                    >
                        <flux:icon.star @class([
                            'size-7 transition',
                            'fill-amber-400 text-amber-400' => $current >= $i,
                            'text-zinc-300 dark:text-zinc-600' => $current < $i,
                        ]) />
                    </button>
                @endfor
            </div>
        @elseif (in_array($type, [QuestionType::OpinionScale, QuestionType::LinearScale, QuestionType::Nps], true))
            @php($min = $type === QuestionType::Nps ? 0 : (int) ($settings['min'] ?? 1))
            @php($max = $type === QuestionType::Nps ? 10 : (int) ($settings['max'] ?? 5))
            @php($current = ($answers[$qid] ?? null) === null ? null : (int) $answers[$qid])
            <div>
                <div class="flex flex-wrap gap-1.5">
                    @for ($i = $min; $i <= $max; $i++)
                        <button
                            type="button"
                            wire:click="$set('{{ $model }}', {{ $i }})"
                            @class([
                                $scaleButtonClasses,
                                'size-9' => $type === QuestionType::Nps,
                                'border-orange-500 bg-orange-600 text-white' => $current === $i,
                                'border-zinc-200 text-zinc-700 hover:border-orange-300 dark:border-zinc-700 dark:text-zinc-300' => $current !== $i,
                            ])
                        >{{ $i }}</button>
                    @endfor
                </div>
                @php($minLabel = ($settings['min_label'] ?? '') !== '' ? $settings['min_label'] : ($type === QuestionType::Nps ? __('Not at all likely') : ''))
                @php($maxLabel = ($settings['max_label'] ?? '') !== '' ? $settings['max_label'] : ($type === QuestionType::Nps ? __('Extremely likely') : ''))
                @if ($minLabel !== '' || $maxLabel !== '')
                    <div class="mt-2 flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ $minLabel }}</span>
                        <span>{{ $maxLabel }}</span>
                    </div>
                @endif
            </div>
        @elseif ($type === QuestionType::Matrix)
            @php($rows = $settings['rows'] ?? [])
            @php($columns = $settings['columns'] ?? [])
            <div class="overflow-x-auto">
                <table class="w-full min-w-96 text-sm">
                    <thead>
                        <tr>
                            <th class="p-2"></th>
                            @foreach ($columns as $column)
                                <th class="p-2 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($rows as $rowIndex => $row)
                            <tr>
                                <td class="p-2 text-zinc-700 dark:text-zinc-300">{{ $row }}</td>
                                @foreach ($columns as $columnIndex => $column)
                                    <td class="p-2 text-center">
                                        <input type="radio" wire:model="{{ $model }}.{{ $rowIndex }}" value="{{ $columnIndex }}" class="size-4 border-zinc-300 text-orange-600 focus:ring-orange-500 dark:border-zinc-600 dark:bg-zinc-800" />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif (in_array($type, [QuestionType::FileUpload, QuestionType::Signature], true))
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-600">
                <flux:icon :icon="$type === QuestionType::FileUpload ? 'arrow-up-tray' : 'pencil-square'" class="size-6 text-zinc-400" />
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $type === QuestionType::FileUpload ? __('File uploads are coming soon.') : __('Signatures are coming soon.') }}
                </p>
            </div>
        @endif
    </div>

    @if ($question['help_text'])
        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $question['help_text'] }}</p>
    @endif

    @if ($errors->first("answers.{$qid}") || $errors->first("answers.{$qid}.*"))
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">
            {{ $errors->first("answers.{$qid}") ?: $errors->first("answers.{$qid}.*") }}
        </p>
    @endif
</fieldset>
