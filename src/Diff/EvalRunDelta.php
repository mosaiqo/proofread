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

    /**
     * Canonical JSON-friendly array shape shared by the MCP tool,
     * evals:compare CLI, and regression webhook generic payload.
     *
     * @return array{
     *     base_run_id: string,
     *     head_run_id: string,
     *     dataset_name: string,
     *     total_cases: int,
     *     regressions: int,
     *     improvements: int,
     *     stable_passes: int,
     *     stable_failures: int,
     *     cost_delta_usd: float,
     *     duration_delta_ms: float,
     *     has_regressions: bool,
     *     cases: list<array<string, mixed>>,
     * }
     */
    public function toArray(): array
    {
        return [
            'base_run_id' => $this->baseRunId,
            'head_run_id' => $this->headRunId,
            'dataset_name' => $this->datasetName,
            'total_cases' => $this->totalCases,
            'regressions' => $this->regressions,
            'improvements' => $this->improvements,
            'stable_passes' => $this->stablePasses,
            'stable_failures' => $this->stableFailures,
            'cost_delta_usd' => $this->costDeltaUsd,
            'duration_delta_ms' => $this->durationDeltaMs,
            'has_regressions' => $this->hasRegressions(),
            'cases' => array_map(
                static fn (CaseDelta $case): array => $case->toArray(),
                $this->cases,
            ),
        ];
    }
}
