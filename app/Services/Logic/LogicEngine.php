<?php

namespace App\Services\Logic;

/**
 * Evaluates question visibility conditions against respondent answers.
 *
 * Logic shape (stored on questions.logic and snapshotted into versions):
 * ['match' => 'all'|'any', 'conditions' => [
 *     ['question_id' => int, 'operator' => string, 'value' => ?string],
 * ]]
 *
 * Triggers are restricted to questions on earlier pages, so their answers
 * are always known by the time the condition is evaluated.
 */
class LogicEngine
{
    public const OPERATORS = [
        'equals',
        'not_equals',
        'contains',
        'greater_than',
        'less_than',
        'is_answered',
        'not_answered',
    ];

    /** Operators that need a comparison value. */
    public const VALUE_OPERATORS = ['equals', 'not_equals', 'contains', 'greater_than', 'less_than'];

    public static function operatorLabel(string $operator): string
    {
        return match ($operator) {
            'equals' => __('equals'),
            'not_equals' => __('does not equal'),
            'contains' => __('contains'),
            'greater_than' => __('is greater than'),
            'less_than' => __('is less than'),
            'is_answered' => __('is answered'),
            'not_answered' => __('is not answered'),
            default => $operator,
        };
    }

    /**
     * Filter snapshot question arrays down to those whose conditions pass.
     *
     * @param  array<int, array<string, mixed>>  $questions
     * @param  array<int|string, mixed>  $answers  keyed by question id
     * @return array<int, array<string, mixed>>
     */
    public function visibleQuestions(array $questions, array $answers): array
    {
        return array_values(array_filter(
            $questions,
            fn (array $question) => $this->conditionsMet($question['logic'] ?? null, $answers),
        ));
    }

    public function conditionsMet(?array $logic, array $answers): bool
    {
        $conditions = $logic['conditions'] ?? [];

        if ($conditions === []) {
            return true;
        }

        $results = array_map(fn (array $condition) => $this->evaluate($condition, $answers), $conditions);

        return ($logic['match'] ?? 'all') === 'any'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }

    protected function evaluate(array $condition, array $answers): bool
    {
        $answer = $answers[(int) ($condition['question_id'] ?? 0)] ?? null;
        $target = $condition['value'] ?? null;
        $answered = $answer !== null && $answer !== '' && $answer !== [];

        return match ($condition['operator'] ?? '') {
            'is_answered' => $answered,
            'not_answered' => ! $answered,
            'equals' => $answered && $this->equals($answer, $target),
            'not_equals' => ! $answered || ! $this->equals($answer, $target),
            'contains' => $answered && $this->contains($answer, $target),
            'greater_than' => $answered && is_numeric($answer) && is_numeric($target) && (float) $answer > (float) $target,
            'less_than' => $answered && is_numeric($answer) && is_numeric($target) && (float) $answer < (float) $target,
            default => true,
        };
    }

    protected function equals(mixed $answer, mixed $target): bool
    {
        if (is_array($answer)) {
            return in_array((string) $target, array_map(strval(...), $answer), true);
        }

        return (string) $answer === (string) $target;
    }

    protected function contains(mixed $answer, mixed $target): bool
    {
        if (is_array($answer)) {
            return in_array((string) $target, array_map(strval(...), $answer), true);
        }

        return str_contains(mb_strtolower((string) $answer), mb_strtolower((string) $target));
    }
}
