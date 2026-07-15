<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizResponse;
use App\Services\Responses\AnswerFormatter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResponseExportController extends Controller
{
    public function __invoke(Quiz $quiz, AnswerFormatter $formatter): StreamedResponse
    {
        Gate::authorize('view', $quiz);

        $version = $quiz->latestVersion();

        abort_unless($version !== null, 404);

        $questions = $version->questionsById();
        $filename = Str::slug($quiz->name).'-responses.csv';

        return response()->streamDownload(function () use ($quiz, $questions, $formatter) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Response ID',
                'Status',
                'Started at',
                'Completed at',
                'Score',
                'Max score',
                'Percentage',
                'Passed',
                'Grade',
                ...array_map(fn (array $question) => $question['title'], array_values($questions)),
            ]);

            $quiz->responses()
                ->with('answers')
                ->latest('started_at')
                ->chunk(200, function ($responses) use ($handle, $questions, $formatter) {
                    foreach ($responses as $response) {
                        /** @var QuizResponse $response */
                        $answers = $response->answers->keyBy('question_id');

                        fputcsv($handle, [
                            $response->id,
                            $response->status,
                            $response->started_at->toDateTimeString(),
                            $response->completed_at?->toDateTimeString() ?? '',
                            $response->score ?? '',
                            $response->max_score ?? '',
                            $response->percentage ?? '',
                            $response->passed === null ? '' : ($response->passed ? 'yes' : 'no'),
                            $response->grade ?? '',
                            ...array_map(
                                fn (int $questionId) => $formatter->format($questions[$questionId], $answers[$questionId]->value ?? null),
                                array_keys($questions),
                            ),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
