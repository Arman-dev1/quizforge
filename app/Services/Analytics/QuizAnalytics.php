<?php

namespace App\Services\Analytics;

use App\Enums\QuestionType;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizResponse;

/**
 * Aggregates analytics for a quiz from stored responses and answers.
 * Question stats are computed against the latest version snapshot;
 * trashed responses are excluded everywhere.
 */
class QuizAnalytics
{
    /**
     * @return array{views: int, starts: int, completions: int, completion_rate: ?int, avg_seconds: ?int}
     */
    public function summary(Quiz $quiz): array
    {
        $starts = $quiz->responses()->count();
        $completions = $quiz->responses()->where('status', QuizResponse::STATUS_COMPLETED)->count();

        $durations = $quiz->responses()
            ->whereNotNull('completed_at')
            ->get(['started_at', 'completed_at']);

        return [
            'views' => (int) $quiz->views()->sum('views'),
            'starts' => $starts,
            'completions' => $completions,
            'completion_rate' => $starts > 0 ? (int) round($completions / $starts * 100) : null,
            'avg_seconds' => $durations->isEmpty()
                ? null
                : (int) round($durations->avg(fn (QuizResponse $response) => abs($response->completed_at->diffInSeconds($response->started_at)))),
        ];
    }

    /**
     * How many respondents reached each page of the latest version.
     *
     * @return array<int, array{title: string, reached: int, rate: int}>
     */
    public function pageFunnel(Quiz $quiz): array
    {
        $version = $quiz->latestVersion();

        if ($version === null) {
            return [];
        }

        $byPage = $quiz->responses()
            ->selectRaw('current_page, count(*) as total')
            ->groupBy('current_page')
            ->pluck('total', 'current_page');

        $starts = $byPage->sum();
        $funnel = [];

        foreach ($version->pages() as $index => $page) {
            $reached = $byPage->filter(fn ($total, $currentPage) => (int) $currentPage >= $index)->sum();

            $funnel[] = [
                'title' => ($page['title'] ?? '') !== '' && $page['title'] !== null
                    ? $page['title']
                    : __('Page :number', ['number' => $index + 1]),
                'reached' => (int) $reached,
                'rate' => $starts > 0 ? (int) round($reached / $starts * 100) : 0,
            ];
        }

        return $funnel;
    }

    /**
     * Per-question stats in version order: answered counts, option
     * distributions (with correct flags), and averages for numeric scales.
     *
     * @return array<int, array<string, mixed>>
     */
    public function questionStats(Quiz $quiz): array
    {
        $version = $quiz->latestVersion();

        if ($version === null) {
            return [];
        }

        $questions = $version->questionsById();

        $stats = [];

        foreach ($questions as $questionId => $question) {
            $stats[$questionId] = [
                'answered' => 0,
                'optionCounts' => [],
                'sum' => 0.0,
                'numericCount' => 0,
            ];
        }

        QuizAnswer::query()
            ->whereHas('response', fn ($query) => $query->where('quiz_id', $quiz->id))
            ->chunkById(500, function ($answers) use (&$stats, $questions) {
                foreach ($answers as $answer) {
                    $questionId = (int) $answer->question_id;

                    if (! isset($questions[$questionId])) {
                        continue;
                    }

                    $stats[$questionId]['answered']++;

                    $type = QuestionType::tryFrom($questions[$questionId]['type']);
                    $value = $answer->value;

                    if ($type?->hasOptions() || $type === QuestionType::YesNo) {
                        foreach ((array) $value as $choice) {
                            $key = (string) $choice;
                            $stats[$questionId]['optionCounts'][$key] = ($stats[$questionId]['optionCounts'][$key] ?? 0) + 1;
                        }
                    } elseif (in_array($type, [QuestionType::Rating, QuestionType::OpinionScale, QuestionType::LinearScale, QuestionType::Nps, QuestionType::Number], true) && is_numeric($value)) {
                        $stats[$questionId]['sum'] += (float) $value;
                        $stats[$questionId]['numericCount']++;
                    }
                }
            });

        $rows = [];

        foreach ($questions as $questionId => $question) {
            $type = QuestionType::tryFrom($question['type']);
            $stat = $stats[$questionId];

            $distribution = [];

            if ($type?->hasOptions()) {
                foreach ($question['options'] as $option) {
                    $count = $stat['optionCounts'][(string) $option['id']] ?? 0;

                    $distribution[] = [
                        'label' => $option['label'],
                        'count' => $count,
                        'pct' => $stat['answered'] > 0 ? (int) round($count / $stat['answered'] * 100) : 0,
                        'is_correct' => (bool) ($option['is_correct'] ?? false),
                    ];
                }
            } elseif ($type === QuestionType::YesNo) {
                foreach (['yes' => __('Yes'), 'no' => __('No')] as $key => $label) {
                    $count = $stat['optionCounts'][$key] ?? 0;

                    $distribution[] = [
                        'label' => $label,
                        'count' => $count,
                        'pct' => $stat['answered'] > 0 ? (int) round($count / $stat['answered'] * 100) : 0,
                        'is_correct' => false,
                    ];
                }
            }

            $rows[] = [
                'id' => $questionId,
                'title' => $question['title'],
                'type' => $type,
                'answered' => $stat['answered'],
                'distribution' => $distribution,
                'average' => $stat['numericCount'] > 0 ? round($stat['sum'] / $stat['numericCount'], 1) : null,
            ];
        }

        return $rows;
    }
}
