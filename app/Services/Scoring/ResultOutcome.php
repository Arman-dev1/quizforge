<?php

namespace App\Services\Scoring;

final readonly class ResultOutcome
{
    public function __construct(
        public bool $showScore,
        public ?string $message,
        public ?string $redirectUrl,
        public ?bool $passed,
        public ?string $grade,
    ) {}
}
