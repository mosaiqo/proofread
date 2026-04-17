<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Coverage;

/**
 * Per-dataset-case coverage statistics: how many shadow captures landed
 * nearest to this case and the average cosine similarity of those matches.
 */
final readonly class CaseCoverage
{
    public function __construct(
        public int $caseIndex,
        public ?string $caseName,
        public int $matchedCaptures,
        public float $avgSimilarity,
    ) {}
}
