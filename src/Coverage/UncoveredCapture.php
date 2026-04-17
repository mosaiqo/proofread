<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Coverage;

/**
 * A shadow capture that is not covered by any dataset case: its max cosine
 * similarity against every case is below the configured threshold.
 */
final readonly class UncoveredCapture
{
    public function __construct(
        public string $captureId,
        public string $inputSnippet,
        public float $maxSimilarity,
        public int $nearestCaseIndex,
    ) {}
}
