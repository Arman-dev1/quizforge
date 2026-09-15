<?php

namespace App\Services\Scoring;

/**
 * Tallies the categories attached to the options a respondent chose.
 *
 * This is what drives "personality"-style quizzes: each option can carry a
 * category (question_options.settings['category']), and the category picked
 * most often decides which outcome the respondent sees. Unlike scoring, it
 * has no notion of right or wrong.
 */
class CategoryScorer
{
    /**
     * @param  array<int, array<string, mixed>>  $pages  snapshot pages (with 'questions')
     * @param  array<int|string, mixed>  $answers  keyed by question id
     * @return array<string, int> category => number of times chosen, highest first
     */
    public function tally(array $pages, array $answers): array
    {
        $counts = [];

        foreach ($pages as $page) {
            foreach ($page['questions'] ?? [] as $question) {
                $answer = $answers[(int) $question['id']] ?? null;

                if ($answer === null || $answer === '' || $answer === []) {
                    continue;
                }

                $chosen = array_map(intval(...), is_array($answer) ? $answer : [$answer]);

                foreach ($question['options'] ?? [] as $option) {
                    if (! in_array((int) $option['id'], $chosen, true)) {
                        continue;
                    }

                    $category = trim((string) ($option['category'] ?? ''));

                    if ($category === '') {
                        continue;
                    }

                    $counts[$category] = ($counts[$category] ?? 0) + 1;
                }
            }
        }

        arsort($counts);

        return $counts;
    }

    /** The winning category, or null when nothing was categorised. */
    public function winner(array $pages, array $answers): ?string
    {
        $counts = $this->tally($pages, $answers);

        return $counts === [] ? null : (string) array_key_first($counts);
    }
}
