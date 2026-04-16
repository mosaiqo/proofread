<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Diff;

final readonly class CaseDelta
{
    /**
     * @param  list<string>  $newFailures
     * @param  list<string>  $fixedFailures
     */
    public function __construct(
        public int $caseIndex,
        public ?string $caseName,
        public bool $basePassed,
        public bool $headPassed,
        public string $status,
        public ?float $baseCostUsd,
        public ?float $headCostUsd,
        public ?float $baseDurationMs,
        public ?float $headDurationMs,
        public array $newFailures,
        public array $fixedFailures,
    ) {}

    /**
     * Canonical JSON-friendly array shape shared by the MCP tool,
     * evals:compare CLI, and regression webhook generic payload.
     *
     * @return array{
     *     case_index: int,
     *     case_name: ?string,
     *     status: string,
     *     base_passed: bool,
     *     head_passed: bool,
     *     base_cost_usd: ?float,
     *     head_cost_usd: ?float,
     *     base_duration_ms: ?float,
     *     head_duration_ms: ?float,
     *     new_failures: list<string>,
     *     fixed_failures: list<string>,
     * }
     */
    public function toArray(): array
    {
        return [
            'case_index' => $this->caseIndex,
            'case_name' => $this->caseName,
            'status' => $this->status,
            'base_passed' => $this->basePassed,
            'head_passed' => $this->headPassed,
            'base_cost_usd' => $this->baseCostUsd,
            'head_cost_usd' => $this->headCostUsd,
            'base_duration_ms' => $this->baseDurationMs,
            'head_duration_ms' => $this->headDurationMs,
            'new_failures' => $this->newFailures,
            'fixed_failures' => $this->fixedFailures,
        ];
    }
}
