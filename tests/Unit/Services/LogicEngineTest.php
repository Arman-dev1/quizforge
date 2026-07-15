<?php

namespace Tests\Unit\Services;

use App\Services\Logic\LogicEngine;
use PHPUnit\Framework\TestCase;

class LogicEngineTest extends TestCase
{
    protected LogicEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new LogicEngine;
    }

    protected function logic(array $conditions, string $match = 'all'): array
    {
        return ['match' => $match, 'conditions' => $conditions];
    }

    public function test_empty_logic_is_always_visible(): void
    {
        $this->assertTrue($this->engine->conditionsMet(null, []));
        $this->assertTrue($this->engine->conditionsMet(['match' => 'all', 'conditions' => []], []));
    }

    public function test_equals_matches_scalars_and_option_arrays(): void
    {
        $logic = $this->logic([['question_id' => 1, 'operator' => 'equals', 'value' => '5']]);

        $this->assertTrue($this->engine->conditionsMet($logic, [1 => 5]));
        $this->assertTrue($this->engine->conditionsMet($logic, [1 => '5']));
        $this->assertTrue($this->engine->conditionsMet($logic, [1 => ['5', '7']]));
        $this->assertFalse($this->engine->conditionsMet($logic, [1 => 6]));
        $this->assertFalse($this->engine->conditionsMet($logic, []));
    }

    public function test_not_equals_passes_when_unanswered(): void
    {
        $logic = $this->logic([['question_id' => 1, 'operator' => 'not_equals', 'value' => 'yes']]);

        $this->assertTrue($this->engine->conditionsMet($logic, []));
        $this->assertTrue($this->engine->conditionsMet($logic, [1 => 'no']));
        $this->assertFalse($this->engine->conditionsMet($logic, [1 => 'yes']));
    }

    public function test_contains_is_case_insensitive_for_strings(): void
    {
        $logic = $this->logic([['question_id' => 1, 'operator' => 'contains', 'value' => 'lara']]);

        $this->assertTrue($this->engine->conditionsMet($logic, [1 => 'I love Laravel']));
        $this->assertFalse($this->engine->conditionsMet($logic, [1 => 'Symfony fan']));
    }

    public function test_numeric_comparisons(): void
    {
        $greater = $this->logic([['question_id' => 1, 'operator' => 'greater_than', 'value' => '7']]);
        $less = $this->logic([['question_id' => 1, 'operator' => 'less_than', 'value' => '3']]);

        $this->assertTrue($this->engine->conditionsMet($greater, [1 => 8]));
        $this->assertFalse($this->engine->conditionsMet($greater, [1 => 7]));
        $this->assertTrue($this->engine->conditionsMet($less, [1 => 2]));
        $this->assertFalse($this->engine->conditionsMet($less, [1 => 'abc']));
    }

    public function test_answered_operators(): void
    {
        $answered = $this->logic([['question_id' => 1, 'operator' => 'is_answered', 'value' => null]]);
        $notAnswered = $this->logic([['question_id' => 1, 'operator' => 'not_answered', 'value' => null]]);

        $this->assertTrue($this->engine->conditionsMet($answered, [1 => 'anything']));
        $this->assertFalse($this->engine->conditionsMet($answered, [1 => '']));
        $this->assertFalse($this->engine->conditionsMet($answered, [1 => []]));
        $this->assertTrue($this->engine->conditionsMet($notAnswered, []));
        $this->assertTrue($this->engine->conditionsMet($notAnswered, [1 => []]));
    }

    public function test_match_all_versus_any(): void
    {
        $conditions = [
            ['question_id' => 1, 'operator' => 'equals', 'value' => 'a'],
            ['question_id' => 2, 'operator' => 'equals', 'value' => 'b'],
        ];

        $answers = [1 => 'a', 2 => 'nope'];

        $this->assertFalse($this->engine->conditionsMet($this->logic($conditions, 'all'), $answers));
        $this->assertTrue($this->engine->conditionsMet($this->logic($conditions, 'any'), $answers));
    }

    public function test_visible_questions_filters_by_logic(): void
    {
        $questions = [
            ['id' => 10, 'logic' => null],
            ['id' => 11, 'logic' => $this->logic([['question_id' => 1, 'operator' => 'equals', 'value' => 'yes']])],
        ];

        $visible = $this->engine->visibleQuestions($questions, [1 => 'no']);

        $this->assertCount(1, $visible);
        $this->assertSame(10, $visible[0]['id']);

        $visible = $this->engine->visibleQuestions($questions, [1 => 'yes']);

        $this->assertCount(2, $visible);
    }
}
