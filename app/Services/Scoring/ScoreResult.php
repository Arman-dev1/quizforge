<?php

namespace App\Services\Scoring;

final readonly class ScoreResult
{
    public function __construct(
        public int $points,
        public int $maxPoints,
        public ?float $percentage,
        public int $correctCount,
        public int $scoredCount,
    ) {}
}
