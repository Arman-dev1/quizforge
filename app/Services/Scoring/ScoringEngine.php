<?php

namespace App\Services\Scoring;

use App\Enums\QuestionType;

/**
 * Scores answers against a version snapshot. Only questions whose type
 * supports correct answers AND that have at least one correct option
 * participate. Pass pages that were already filtered by the LogicEngine
 * so hidden questions don't inflate the maximum.
 */
class ScoringEngine
{
    /**
     * @param  array<int, array<string, mixed>>  $pages  snapshot pages (with 'questions')
     * @param  array<int|string, mixed>  $answers  keyed by question id
     */
    public function score(array $pages, array $answers): ScoreResult
    {
        $points = 0;
        $max = 0;
        $correctCount = 0;
        $scoredCount = 0;

        foreach ($pages as $page) {
            foreach ($page['questions'] ?? [] as $question) {
                if (! QuestionType::from($question['type'])->supportsCorrectAnswers()) {
                    continue;
                }

                $correctIds = array_values(array_map(
                    fn (array $option) => (int) $option['id'],
                    array_filter($question['options'] ?? [], fn (array $option) => $option['is_correct'] ?? false),
                ));

                if ($correctIds === []) {
                    continue;
                }

                $scoredCount++;

                $questionPoints = max(0, (int) (($question['settings']['points'] ?? null) ?? 1));
                $negativePoints = max(0, (int) (($question['settings']['negative_points'] ?? null) ?? 0));

                $max += $questionPoints;

                $answer = $answers[(int) $question['id']] ?? null;

                if ($answer === null || $answer === '' || $answer === []) {
                    continue;
                }

                $given = array_map(intval(...), is_array($answer) ? $answer : [$answer]);

                sort($given);
                sort($correctIds);

                if ($given === $correctIds) {
                    $points += $questionPoints;
                    $correctCount++;
                } else {
                    $points -= $negativePoints;
                }
            }
        }

        return new ScoreResult(
            points: $points,
            maxPoints: $max,
            percentage: $max > 0 ? round(max(0, $points) / $max * 100, 1) : null,
            correctCount: $correctCount,
            scoredCount: $scoredCount,
        );
    }
}
