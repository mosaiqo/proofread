<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Diff;

final readonly class EvalRunDelta
{
    /**
     * @param  list<CaseDelta>  $cases
     */
    public function __construct(
        public string $baseRunId,
        public string $headRunId,
        public string $datasetName,
        public int $totalCases,
        public int $regressions,
        public int $improvements,
        public int $stableFailures,
        public int $stablePasses,
        public float $costDeltaUsd,
        public float $durationDeltaMs,
        public array $cases,
    ) {}

    public function hasRegressions(): bool
    {
        return $this->regressions > 0;
    }
}
