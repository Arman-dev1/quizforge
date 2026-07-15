<?php

namespace App\Services\Scoring;

/**
 * Maps a score onto the quiz's configured outcome: pass/fail against a
 * threshold, a grade band, the thank-you message, and optional redirect.
 *
 * Results config lives in quiz settings:
 * ['results' => [
 *     'show_score' => bool,
 *     'pass_percentage' => ?numeric,
 *     'grades' => [['min' => numeric, 'label' => string], …],
 *     'thank_you_message' => ?string,
 *     'redirect_url' => ?string,
 * ]]
 */
class ResultResolver
{
    public function resolve(array $quizSettings, ?ScoreResult $score): ResultOutcome
    {
        $results = $quizSettings['results'] ?? [];

        $passed = null;
        $grade = null;

        if ($score !== null && $score->maxPoints > 0) {
            $percentage = $score->percentage ?? 0.0;

            $threshold = $results['pass_percentage'] ?? null;

            if ($threshold !== null && $threshold !== '') {
                $passed = $percentage >= (float) $threshold;
            }

            $bands = collect($results['grades'] ?? [])
                ->filter(fn ($band) => isset($band['min'], $band['label']))
                ->sortByDesc(fn ($band) => (float) $band['min']);

            foreach ($bands as $band) {
                if ($percentage >= (float) $band['min']) {
                    $grade = $band['label'];
                    break;
                }
            }
        }

        return new ResultOutcome(
            showScore: (bool) ($results['show_score'] ?? true),
            message: ($results['thank_you_message'] ?? '') !== '' ? $results['thank_you_message'] : null,
            redirectUrl: ($results['redirect_url'] ?? '') !== '' ? $results['redirect_url'] : null,
            passed: $passed,
            grade: $grade,
        );
    }
}
