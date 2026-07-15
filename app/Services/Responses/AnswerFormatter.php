<?php

namespace App\Services\Responses;

use App\Enums\QuestionType;

/**
 * Turns stored answer values into human-readable strings using the
 * question definition from the response's own version snapshot —
 * option labels instead of ids, matrix rows/columns by name, etc.
 */
class AnswerFormatter
{
    public function format(?array $question, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        if ($question === null) {
            return $this->scalar($value);
        }

        $type = QuestionType::tryFrom($question['type'] ?? '');
        $labels = [];

        foreach ($question['options'] ?? [] as $option) {
            $labels[(int) $option['id']] = $option['label'];
        }

        $optionLabel = fn ($id) => $labels[(int) $id] ?? ('#'.$id);

        return match ($type) {
            QuestionType::SingleChoice,
            QuestionType::Dropdown,
            QuestionType::ImageChoice => $optionLabel($value),

            QuestionType::MultipleChoice => collect((array) $value)->map($optionLabel)->implode(', '),

            QuestionType::Ranking => collect((array) $value)->values()
                ->map(fn ($id, $index) => ($index + 1).'. '.$optionLabel($id))
                ->implode(', '),

            QuestionType::YesNo => $value === 'yes' ? __('Yes') : __('No'),

            QuestionType::Matrix => $this->matrix($question, (array) $value),

            QuestionType::Address => collect(['street', 'city', 'state', 'postal_code', 'country'])
                ->map(fn (string $key) => $value[$key] ?? null)
                ->filter(fn ($part) => $part !== null && $part !== '')
                ->implode(', '),

            default => $this->scalar($value),
        };
    }

    protected function matrix(array $question, array $value): string
    {
        $rows = $question['settings']['rows'] ?? [];
        $columns = $question['settings']['columns'] ?? [];

        return collect($value)
            ->map(fn ($columnIndex, $rowIndex) => ($rows[$rowIndex] ?? ('#'.$rowIndex)).': '.($columns[$columnIndex] ?? ('#'.$columnIndex)))
            ->implode('; ');
    }

    protected function scalar(mixed $value): string
    {
        return is_array($value) ? json_encode($value) : (string) $value;
    }
}
