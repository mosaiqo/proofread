<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Support;

class AssertionResult
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  ?float  $score  Arbitrary numeric score. The consumer interprets
     *                         the meaning based on the assertion. Common
     *                         conventions:
     *                         - [0, 1] for probabilities, pass rates, confidence.
     *                         - [-1, 1] for cosine similarity.
     *                         - Other ranges are allowed per assertion (magnitudes,
     *                         z-scores, etc.). `null` means "no score produced".
     */
    protected function __construct(
        public readonly bool $passed,
        public readonly string $reason,
        public readonly ?float $score = null,
        public readonly array $metadata = [],
    ) {}

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
