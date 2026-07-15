<?php

namespace Tests\Unit\Services;

use App\Services\Scoring\ScoringEngine;
use PHPUnit\Framework\TestCase;

class ScoringEngineTest extends TestCase
{
    protected ScoringEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new ScoringEngine;
    }

    protected function singleChoice(int $id, array $settings = []): array
    {
        return [
            'id' => $id,
            'type' => 'single_choice',
            'settings' => $settings,
            'options' => [
                ['id' => $id * 10 + 1, 'label' => 'Right', 'is_correct' => true],
                ['id' => $id * 10 + 2, 'label' => 'Wrong', 'is_correct' => false],
            ],
        ];
    }

    protected function pages(array $questions): array
    {
        return [['questions' => $questions]];
    }

    public function test_correct_answers_earn_points(): void
    {
        $result = $this->engine->score(
            $this->pages([$this->singleChoice(1, ['points' => 3])]),
            [1 => 11],
        );

        $this->assertSame(3, $result->points);
        $this->assertSame(3, $result->maxPoints);
        $this->assertSame(100.0, $result->percentage);
        $this->assertSame(1, $result->correctCount);
    }

    public function test_wrong_answers_apply_negative_marking(): void
    {
        $result = $this->engine->score(
            $this->pages([
                $this->singleChoice(1, ['points' => 2]),
                $this->singleChoice(2, ['points' => 3, 'negative_points' => 1]),
            ]),
            [1 => 11, 2 => 22],
        );

        $this->assertSame(1, $result->points); // 2 - 1
        $this->assertSame(5, $result->maxPoints);
        $this->assertSame(20.0, $result->percentage);
        $this->assertSame(1, $result->correctCount);
        $this->assertSame(2, $result->scoredCount);
    }

    public function test_unanswered_questions_score_zero_without_penalty(): void
    {
        $result = $this->engine->score(
            $this->pages([$this->singleChoice(1, ['points' => 2, 'negative_points' => 1])]),
            [],
        );

        $this->assertSame(0, $result->points);
        $this->assertSame(2, $result->maxPoints);
        $this->assertSame(0.0, $result->percentage);
    }

    public function test_multiple_choice_requires_the_exact_correct_set(): void
    {
        $question = [
            'id' => 1,
            'type' => 'multiple_choice',
            'settings' => ['points' => 4],
            'options' => [
                ['id' => 11, 'label' => 'A', 'is_correct' => true],
                ['id' => 12, 'label' => 'B', 'is_correct' => true],
                ['id' => 13, 'label' => 'C', 'is_correct' => false],
            ],
        ];

        $exact = $this->engine->score($this->pages([$question]), [1 => [12, 11]]);
        $partial = $this->engine->score($this->pages([$question]), [1 => [11]]);
        $withWrong = $this->engine->score($this->pages([$question]), [1 => [11, 12, 13]]);

        $this->assertSame(4, $exact->points);
        $this->assertSame(0, $partial->points);
        $this->assertSame(0, $withWrong->points);
    }

    public function test_questions_without_correct_options_are_not_scored(): void
    {
        $survey = [
            'id' => 1,
            'type' => 'single_choice',
            'settings' => [],
            'options' => [['id' => 11, 'label' => 'Blue', 'is_correct' => false]],
        ];

        $text = ['id' => 2, 'type' => 'short_text', 'settings' => [], 'options' => []];

        $result = $this->engine->score($this->pages([$survey, $text]), [1 => 11, 2 => 'hello']);

        $this->assertSame(0, $result->maxPoints);
        $this->assertSame(0, $result->scoredCount);
        $this->assertNull($result->percentage);
    }

    public function test_percentage_clamps_negative_totals_to_zero(): void
    {
        $result = $this->engine->score(
            $this->pages([$this->singleChoice(1, ['points' => 2, 'negative_points' => 5])]),
            [1 => 12],
        );

        $this->assertSame(-5, $result->points);
        $this->assertSame(0.0, $result->percentage);
    }
}
