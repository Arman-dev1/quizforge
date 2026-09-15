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
        /** Headline of the matched result screen, e.g. "You're a Planner". */
        public ?string $title = null,
        /** Sanitized rich-text body for the matched result screen. */
        public ?string $description = null,
        /** Which category won, when the quiz resolves outcomes by category. */
        public ?string $category = null,
    ) {}
}
