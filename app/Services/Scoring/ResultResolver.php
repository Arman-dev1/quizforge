<?php

namespace App\Services\Scoring;

/**
 * Decides what a respondent sees when they finish: pass/fail against a
 * threshold, a grade band, and — when the quiz defines them — a full result
 * screen with its own title and rich-text body.
 *
 * Results config lives in quiz settings:
 * ['results' => [
 *     'show_score' => bool,
 *     'pass_percentage' => ?numeric,
 *     'grades' => [['min' => numeric, 'label' => string], …],
 *     'thank_you_message' => ?string,   // fallback when nothing matches
 *     'redirect_url' => ?string,        // fallback redirect
 *     'mode' => 'simple'|'score'|'category',
 *     'outcomes' => [[
 *         'key' => string, 'title' => string, 'description' => string (HTML),
 *         'min' => ?numeric, 'max' => ?numeric,   // score mode, percentages
 *         'category' => ?string,                   // category mode
 *         'redirect_url' => ?string,
 *     ], …],
 * ]]
 */
class ResultResolver
{
    public const MODE_SIMPLE = 'simple';

    public const MODE_SCORE = 'score';

    public const MODE_CATEGORY = 'category';

    /**
     * @param  array<string, int>  $categoryTally  category => hits, highest first
     */
    public function resolve(array $quizSettings, ?ScoreResult $score, array $categoryTally = []): ResultOutcome
    {
        $results = $quizSettings['results'] ?? [];

        $passed = null;
        $grade = null;
        $percentage = null;

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

        $matched = $this->matchOutcome($results, $percentage, $categoryTally);

        return new ResultOutcome(
            showScore: (bool) ($results['show_score'] ?? true),
            message: ($results['thank_you_message'] ?? '') !== '' ? $results['thank_you_message'] : null,
            // An outcome's own redirect wins over the quiz-wide fallback.
            redirectUrl: ($matched['redirect_url'] ?? '') !== ''
                ? $matched['redirect_url']
                : (($results['redirect_url'] ?? '') !== '' ? $results['redirect_url'] : null),
            passed: $passed,
            grade: $grade,
            title: ($matched['title'] ?? '') !== '' ? $matched['title'] : null,
            description: ($matched['description'] ?? '') !== '' ? $matched['description'] : null,
            category: $matched['category'] ?? null,
        );
    }

    /**
     * Find the result screen for this response, or an empty array.
     *
     * @return array<string, mixed>
     */
    protected function matchOutcome(array $results, ?float $percentage, array $categoryTally): array
    {
        $mode = $results['mode'] ?? self::MODE_SIMPLE;
        $outcomes = array_values(array_filter($results['outcomes'] ?? [], 'is_array'));

        if ($outcomes === [] || $mode === self::MODE_SIMPLE) {
            return [];
        }

        if ($mode === self::MODE_CATEGORY) {
            $winner = $categoryTally === [] ? null : (string) array_key_first($categoryTally);

            if ($winner === null) {
                return [];
            }

            foreach ($outcomes as $outcome) {
                if (strcasecmp(trim((string) ($outcome['category'] ?? '')), $winner) === 0) {
                    return $outcome + ['category' => $winner];
                }
            }

            return [];
        }

        // Score mode: highest matching band wins, so overlapping ranges
        // resolve predictably rather than by list order.
        if ($percentage === null) {
            return [];
        }

        $candidates = collect($outcomes)
            ->filter(function (array $outcome) use ($percentage) {
                $min = $outcome['min'] ?? null;
                $max = $outcome['max'] ?? null;

                return ($min === null || $min === '' || $percentage >= (float) $min)
                    && ($max === null || $max === '' || $percentage <= (float) $max);
            })
            ->sortByDesc(fn (array $outcome) => (float) ($outcome['min'] ?? 0));

        return $candidates->first() ?? [];
    }
}
