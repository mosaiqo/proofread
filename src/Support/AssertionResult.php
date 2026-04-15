<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Support;

use InvalidArgumentException;

final readonly class AssertionResult
{
    private function __construct(
        public bool $passed,
        public string $reason,
        public ?float $score = null,
    ) {
        if ($score !== null && ($score < 0.0 || $score > 1.0)) {
            throw new InvalidArgumentException(
                "Score must be between 0.0 and 1.0, got {$score}."
            );
        }
    }

    public static function pass(string $reason = '', ?float $score = null): self
    {
        return new self(true, $reason, $score);
    }

    public static function fail(string $reason, ?float $score = null): self
    {
        return new self(false, $reason, $score);
    }
}
