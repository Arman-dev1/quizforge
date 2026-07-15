<?php

namespace Tests\Unit\Services;

use App\Services\Scoring\ResultResolver;
use App\Services\Scoring\ScoreResult;
use PHPUnit\Framework\TestCase;

class ResultResolverTest extends TestCase
{
    protected ResultResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ResultResolver;
    }

    protected function score(float $percentage, int $max = 10): ScoreResult
    {
        return new ScoreResult(
            points: (int) round($percentage / 100 * $max),
            maxPoints: $max,
            percentage: $percentage,
            correctCount: 0,
            scoredCount: 0,
        );
    }

    public function test_defaults_without_configuration(): void
    {
        $outcome = $this->resolver->resolve([], $this->score(50));

        $this->assertTrue($outcome->showScore);
        $this->assertNull($outcome->passed);
        $this->assertNull($outcome->grade);
        $this->assertNull($outcome->message);
        $this->assertNull($outcome->redirectUrl);
    }

    public function test_pass_threshold(): void
    {
        $settings = ['results' => ['pass_percentage' => 60]];

        $this->assertTrue($this->resolver->resolve($settings, $this->score(60))->passed);
        $this->assertFalse($this->resolver->resolve($settings, $this->score(59.9))->passed);
    }

    public function test_grade_bands_pick_the_highest_matching_band(): void
    {
        $settings = ['results' => ['grades' => [
            ['min' => 0, 'label' => 'C'],
            ['min' => 80, 'label' => 'A'],
            ['min' => 50, 'label' => 'B'],
        ]]];

        $this->assertSame('A', $this->resolver->resolve($settings, $this->score(85))->grade);
        $this->assertSame('B', $this->resolver->resolve($settings, $this->score(50))->grade);
        $this->assertSame('C', $this->resolver->resolve($settings, $this->score(10))->grade);
    }

    public function test_no_score_means_no_pass_or_grade(): void
    {
        $settings = ['results' => [
            'pass_percentage' => 60,
            'grades' => [['min' => 0, 'label' => 'C']],
            'thank_you_message' => 'Cheers!',
            'redirect_url' => 'https://example.com',
        ]];

        $outcome = $this->resolver->resolve($settings, null);

        $this->assertNull($outcome->passed);
        $this->assertNull($outcome->grade);
        $this->assertSame('Cheers!', $outcome->message);
        $this->assertSame('https://example.com', $outcome->redirectUrl);
    }

    public function test_zero_max_points_yields_no_pass_or_grade(): void
    {
        $settings = ['results' => ['pass_percentage' => 60]];
        $score = new ScoreResult(0, 0, null, 0, 0);

        $this->assertNull($this->resolver->resolve($settings, $score)->passed);
    }
}
