<?php

namespace App\Services\Player;

use App\Enums\QuestionType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Builds and runs validation for respondent answers against the
 * question definitions of a published quiz version page.
 */
class AnswerValidator
{
    /**
     * @param  array<int, array<string, mixed>>  $questions  question arrays from a version snapshot
     * @param  array<int|string, mixed>  $answers  keyed by question id
     *
     * @throws ValidationException
     */
    public function validate(array $questions, array $answers): void
    {
        $rules = [];
        $attributes = [];

        foreach ($questions as $question) {
            $key = 'answers.'.$question['id'];
            $attributes[$key] = $question['title'] !== '' ? $question['title'] : __('this question');

            foreach ($this->rulesFor($question) as $suffix => $questionRules) {
                $ruleKey = $suffix === '' ? $key : $key.$suffix;
                $rules[$ruleKey] = $questionRules;
                $attributes[$ruleKey] = $attributes[$key];
            }
        }

        Validator::make(['answers' => $answers], $rules, [], $attributes)->validate();
    }

    /**
     * Rules keyed by a key suffix ('' = the answer itself, '.*' = array items, …).
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rulesFor(array $question): array
    {
        $type = QuestionType::from($question['type']);
        $required = $question['is_required'] ? 'required' : 'nullable';
        $settings = $question['settings'] ?? [];
        $optionIds = array_column($question['options'] ?? [], 'id');

        return match ($type) {
            QuestionType::SingleChoice,
            QuestionType::Dropdown,
            QuestionType::ImageChoice => ['' => [$required, 'integer', Rule::in($optionIds)]],

            QuestionType::MultipleChoice => [
                '' => $question['is_required'] ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
                '.*' => ['integer', 'distinct', Rule::in($optionIds)],
            ],

            // A ranking is only meaningful if every option is placed exactly
            // once — a partial or duplicated list is not a valid ordering.
            QuestionType::Ranking => [
                '' => [$required, 'array', 'size:'.count($optionIds)],
                '.*' => ['integer', 'distinct', Rule::in($optionIds)],
            ],

            QuestionType::YesNo => ['' => [$required, Rule::in(['yes', 'no'])]],

            QuestionType::ShortText, QuestionType::LongText => [
                '' => array_filter([
                    $required,
                    'string',
                    ($settings['max_length'] ?? null) ? 'max:'.$settings['max_length'] : 'max:10000',
                ]),
            ],

            QuestionType::Email => ['' => [$required, 'string', 'email', 'max:255']],
            QuestionType::Phone => ['' => [$required, 'string', 'max:30']],
            QuestionType::Website => ['' => [$required, 'string', 'url', 'max:255']],

            QuestionType::Number => [
                '' => array_values(array_filter([
                    $required,
                    'numeric',
                    ($settings['min'] ?? null) !== null ? 'min:'.$settings['min'] : null,
                    ($settings['max'] ?? null) !== null ? 'max:'.$settings['max'] : null,
                ])),
            ],

            QuestionType::Rating => ['' => [$required, 'integer', 'between:1,'.(int) ($settings['max'] ?? 5)]],

            QuestionType::OpinionScale, QuestionType::LinearScale => [
                '' => [$required, 'integer', 'between:'.(int) ($settings['min'] ?? 1).','.(int) ($settings['max'] ?? 5)],
            ],

            QuestionType::Nps => ['' => [$required, 'integer', 'between:0,10']],

            QuestionType::Date => ['' => [$required, 'date']],
            QuestionType::Time => ['' => [$required, 'date_format:H:i']],

            QuestionType::Address => [
                '' => [$required, 'array'],
                '.street' => [$question['is_required'] ? 'required' : 'nullable', 'string', 'max:255'],
                '.city' => ['nullable', 'string', 'max:100'],
                '.state' => ['nullable', 'string', 'max:100'],
                '.postal_code' => ['nullable', 'string', 'max:20'],
                '.country' => ['nullable', 'string', 'max:100'],
            ],

            QuestionType::Matrix => $this->matrixRules($question, $settings),

            // Not yet supported in the public player.
            QuestionType::FileUpload, QuestionType::Signature => ['' => ['nullable']],
        };
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function matrixRules(array $question, array $settings): array
    {
        $rows = $settings['rows'] ?? [];
        $columnIndexes = array_keys($settings['columns'] ?? []);
        $required = $question['is_required'];

        $rules = ['' => [$required ? 'required' : 'nullable', 'array']];

        foreach (array_keys($rows) as $rowIndex) {
            $rules['.'.$rowIndex] = [$required ? 'required' : 'nullable', 'integer', Rule::in($columnIndexes)];
        }

        return $rules;
    }
}
