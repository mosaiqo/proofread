<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Simulation;

/**
 * Projected cost for a single model computed from a set of shadow captures.
 *
 * Covered captures are the ones that had usable token data and produced a
 * cost via the pricing table. Skipped captures either lacked tokens entirely
 * or the model was not present in the pricing table, so they do not
 * contribute to the total.
 */
final readonly class CostProjection
{
    public function __construct(
        public string $model,
        public float $totalCost,
        public float $perCaptureCost,
        public int $coveredCaptures,
        public int $skippedCaptures,
    ) {}
}
