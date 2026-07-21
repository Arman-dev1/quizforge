@php
    use App\Enums\QuestionType;

    $settings = $question->settings ?? [];
    $inputClasses = 'w-full rounded-lg border-zinc-300 bg-white text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white';
    $choiceCardClasses = 'flex cursor-pointer items-center gap-3 rounded-xl border border-zinc-200 p-3 transition hover:border-teal-300 hover:bg-teal-50/40 has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50 dark:border-zinc-700 dark:hover:border-teal-800 dark:hover:bg-teal-950/20 dark:has-[:checked]:border-teal-500 dark:has-[:checked]:bg-teal-950/40';
    $scaleButtonClasses = 'flex size-10 items-center justify-center rounded-lg border text-sm font-medium transition';
    $fieldName = 'preview-'.$question->id;
@endphp

<fieldset>
    <legend class="block text-base font-medium text-zinc-900 dark:text-white">
        {{ $question->title !== '' ? $question->title : __('Untitled question') }}
        @if ($question->is_required)
            <span class="text-red-500" aria-hidden="true">*</span>
        @endif
    </legend>

    @if ($question->description)
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $question->description }}</p>
    @endif

    <div class="mt-3">
        @if ($question->type === QuestionType::SingleChoice)
            <div class="space-y-2">
                @foreach ($question->options as $option)
                    <label class="{{ $choiceCardClasses }}">
                        <input type="radio" name="{{ $fieldName }}" class="size-4 border-zinc-300 text-teal-600 focus:ring-teal-500 dark:border-zinc-600 dark:bg-zinc-800" />
                        <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ $option->label }}</span>
                    </label>
                @endforeach
            </div>
        @elseif ($question->type === QuestionType::MultipleChoice)
            <div class="space-y-2">
                @foreach ($question->options as $option)
                    <label class="{{ $choiceCardClasses }}">
                        <input type="checkbox" name="{{ $fieldName }}[]" class="size-4 rounded border-zinc-300 text-teal-600 focus:ring-teal-500 dark:border-zinc-600 dark:bg-zinc-800" />
                        <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ $option->label }}</span>
                    </label>
                @endforeach
            </div>
        @elseif ($question->type === QuestionType::Dropdown)
            <select name="{{ $fieldName }}" class="{{ $inputClasses }}">
                <option value="">{{ $question->placeholder ?: __('Select an option…') }}</option>
                @foreach ($question->options as $option)
                    <option>{{ $option->label }}</option>
                @endforeach
            </select>
        @elseif ($question->type === QuestionType::ImageChoice)
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($question->options as $option)
                    <label class="cursor-pointer rounded-xl border border-zinc-200 p-2 text-center transition hover:border-teal-300 has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50 dark:border-zinc-700 dark:has-[:checked]:bg-teal-950/40">
                        <input type="radio" name="{{ $fieldName }}" class="sr-only" />
                        <span class="flex aspect-video items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                            <flux:icon.photo class="size-6 text-zinc-400" />
                        </span>
                        <span class="mt-2 block truncate text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $option->label }}</span>
                    </label>
                @endforeach
            </div>
        @elseif ($question->type === QuestionType::YesNo)
            <div class="grid grid-cols-2 gap-3">
                @foreach ([__('Yes'), __('No')] as $answer)
                    <label class="{{ $choiceCardClasses }} justify-center">
                        <input type="radio" name="{{ $fieldName }}" class="sr-only" />
                        <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $answer }}</span>
                    </label>
                @endforeach
            </div>
        @elseif ($question->type === QuestionType::Ranking)
            <ol class="space-y-2">
                @foreach ($question->options as $option)
                    <li class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <span class="flex size-6 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $loop->iteration }}</span>
                        <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ $option->label }}</span>
                        <flux:icon.bars-2 class="ml-auto size-4 text-zinc-300 dark:text-zinc-600" />
                    </li>
                @endforeach
            </ol>
        @elseif ($question->type === QuestionType::LongText)
            <textarea name="{{ $fieldName }}" rows="4" placeholder="{{ $question->placeholder ?: __('Type your answer…') }}" @if($settings['max_length'] ?? false) maxlength="{{ $settings['max_length'] }}" @endif class="{{ $inputClasses }}"></textarea>
        @elseif (in_array($question->type, [QuestionType::ShortText, QuestionType::Email, QuestionType::Phone, QuestionType::Website], true))
            @php($inputType = match ($question->type) {
                QuestionType::Email => 'email',
                QuestionType::Phone => 'tel',
                QuestionType::Website => 'url',
                default => 'text',
            })
            <input type="{{ $inputType }}" name="{{ $fieldName }}" placeholder="{{ $question->placeholder ?: __('Type your answer…') }}" @if($settings['max_length'] ?? false) maxlength="{{ $settings['max_length'] }}" @endif class="{{ $inputClasses }}" />
        @elseif ($question->type === QuestionType::Number)
            <input type="number" name="{{ $fieldName }}" placeholder="{{ $question->placeholder ?: '0' }}" @if(($settings['min'] ?? null) !== null) min="{{ $settings['min'] }}" @endif @if(($settings['max'] ?? null) !== null) max="{{ $settings['max'] }}" @endif class="{{ $inputClasses }} max-w-48" />
        @elseif ($question->type === QuestionType::Date)
            <input type="date" name="{{ $fieldName }}" class="{{ $inputClasses }} max-w-48" />
        @elseif ($question->type === QuestionType::Time)
            <input type="time" name="{{ $fieldName }}" class="{{ $inputClasses }} max-w-48" />
        @elseif ($question->type === QuestionType::Address)
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <input type="text" placeholder="{{ __('Street address') }}" class="{{ $inputClasses }} sm:col-span-2" />
                <input type="text" placeholder="{{ __('City') }}" class="{{ $inputClasses }}" />
                <input type="text" placeholder="{{ __('State / Province') }}" class="{{ $inputClasses }}" />
                <input type="text" placeholder="{{ __('Postal code') }}" class="{{ $inputClasses }}" />
                <input type="text" placeholder="{{ __('Country') }}" class="{{ $inputClasses }}" />
            </div>
        @elseif ($question->type === QuestionType::Rating)
            @php($max = (int) ($settings['max'] ?? 5))
            <div x-data="{ value: 0, hover: 0 }" class="flex items-center gap-1">
                @for ($i = 1; $i <= $max; $i++)
                    <button
                        type="button"
                        x-on:click="value = {{ $i }}"
                        x-on:mouseenter="hover = {{ $i }}"
                        x-on:mouseleave="hover = 0"
                        aria-label="{{ __(':count stars', ['count' => $i]) }}"
                    >
                        <flux:icon.star
                            class="size-7 transition"
                            x-bind:class="(hover >= {{ $i }} || (! hover && value >= {{ $i }})) ? 'fill-amber-400 text-amber-400' : 'text-zinc-300 dark:text-zinc-600'"
                        />
                    </button>
                @endfor
            </div>
        @elseif (in_array($question->type, [QuestionType::OpinionScale, QuestionType::LinearScale], true))
            @php($min = (int) ($settings['min'] ?? 1))
            @php($max = (int) ($settings['max'] ?? 5))
            <div x-data="{ value: null }">
                <div class="flex flex-wrap gap-2">
                    @for ($i = $min; $i <= $max; $i++)
                        <button
                            type="button"
                            x-on:click="value = {{ $i }}"
                            x-bind:class="value === {{ $i }} ? 'border-teal-500 bg-teal-600 text-white' : 'border-zinc-200 text-zinc-700 hover:border-teal-300 dark:border-zinc-700 dark:text-zinc-300'"
                            class="{{ $scaleButtonClasses }}"
                        >{{ $i }}</button>
                    @endfor
                </div>
                @if (($settings['min_label'] ?? '') !== '' || ($settings['max_label'] ?? '') !== '')
                    <div class="mt-2 flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ $settings['min_label'] ?? '' }}</span>
                        <span>{{ $settings['max_label'] ?? '' }}</span>
                    </div>
                @endif
            </div>
        @elseif ($question->type === QuestionType::Nps)
            <div x-data="{ value: null }">
                <div class="flex flex-wrap gap-1.5">
                    @for ($i = 0; $i <= 10; $i++)
                        <button
                            type="button"
                            x-on:click="value = {{ $i }}"
                            x-bind:class="value === {{ $i }} ? 'border-teal-500 bg-teal-600 text-white' : 'border-zinc-200 text-zinc-700 hover:border-teal-300 dark:border-zinc-700 dark:text-zinc-300'"
                            class="{{ $scaleButtonClasses }} size-9"
                        >{{ $i }}</button>
                    @endfor
                </div>
                <div class="mt-2 flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                    <span>{{ ($settings['min_label'] ?? '') !== '' ? $settings['min_label'] : __('Not at all likely') }}</span>
                    <span>{{ ($settings['max_label'] ?? '') !== '' ? $settings['max_label'] : __('Extremely likely') }}</span>
                </div>
            </div>
        @elseif ($question->type === QuestionType::FileUpload)
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-600">
                <flux:icon.arrow-up-tray class="size-6 text-zinc-400" />
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Drag a file here or click to browse') }}</p>
                <p class="mt-1 text-xs text-zinc-400">{{ __('Uploads are disabled in preview') }}</p>
            </div>
        @elseif ($question->type === QuestionType::Signature)
            <div class="flex h-32 items-end justify-between rounded-xl border border-zinc-300 bg-zinc-50 p-3 dark:border-zinc-600 dark:bg-zinc-800/60">
                <span class="text-xs text-zinc-400">{{ __('Sign here') }}</span>
                <flux:icon.pencil class="size-4 text-zinc-400" />
            </div>
        @elseif ($question->type === QuestionType::Matrix)
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
                                @foreach ($columns as $column)
                                    <td class="p-2 text-center">
                                        <input type="radio" name="{{ $fieldName }}-{{ $rowIndex }}" class="size-4 border-zinc-300 text-teal-600 focus:ring-teal-500 dark:border-zinc-600 dark:bg-zinc-800" />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($question->help_text)
        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $question->help_text }}</p>
    @endif
</fieldset>
