<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Coverage;

use DateTimeImmutable;
use Mosaiqo\Proofread\Clustering\FailureCluster;

/**
 * Aggregate coverage report: for one agent, over a time window, it answers
 * "which dataset cases reflect production traffic and which captures have
 * no dataset analogue?".
 */
final readonly class CoverageReport
{
    /**
     * @param  list<CaseCoverage>  $caseCoverage
     * @param  list<UncoveredCapture>  $uncovered
     * @param  list<FailureCluster>  $uncoveredClusters
     */
    public function __construct(
        public string $agentClass,
        public string $datasetName,
        public int $totalCaptures,
        public int $coveredCount,
        public int $uncoveredCount,
        public int $skippedCount,
        public float $threshold,
        public array $caseCoverage,
        public array $uncovered,
        public array $uncoveredClusters,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {}

    public function coverageRatio(): float
    {
        $denom = max(1, $this->coveredCount + $this->uncoveredCount);

        return $this->coveredCount / $denom;
    }
}
