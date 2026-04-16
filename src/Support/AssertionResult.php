<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Support;

use InvalidArgumentException;

class AssertionResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function __construct(
        public readonly bool $passed,
        public readonly string $reason,
        public readonly ?float $score = null,
        public readonly array $metadata = [],
    ) {
        if ($score !== null && ($score < 0.0 || $score > 1.0)) {
            throw new InvalidArgumentException(
                "Score must be between 0.0 and 1.0, got {$score}."
            );
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function pass(string $reason = '', ?float $score = null, array $metadata = []): self
    {
        return new self(true, $reason, $score, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function fail(string $reason, ?float $score = null, array $metadata = []): self
    {
        return new self(false, $reason, $score, $metadata);
    }
}
