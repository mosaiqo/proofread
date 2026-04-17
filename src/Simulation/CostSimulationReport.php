<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Simulation;

use DateTimeImmutable;

/**
 * Aggregate cost-simulation report for one agent over a time window: the
 * current model's projected cost plus projections for alternative models.
 */
final readonly class CostSimulationReport
{
    /**
     * @param  array<string, CostProjection>  $projections  Alternatives keyed by model name (excludes $current).
     */
    public function __construct(
        public string $agentClass,
        public CostProjection $current,
        public array $projections,
        public int $totalCaptures,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {}

    public function cheapestAlternative(): ?CostProjection
    {
        if ($this->projections === []) {
            return null;
        }

        $cheapest = null;
        foreach ($this->projections as $projection) {
            if ($projection->coveredCaptures === 0) {
                continue;
            }

            if ($cheapest === null || $projection->totalCost < $cheapest->totalCost) {
                $cheapest = $projection;
            }
        }

        return $cheapest;
    }

    public function savingsVs(string $model): ?float
    {
        if (! array_key_exists($model, $this->projections)) {
            return null;
        }

        return $this->current->totalCost - $this->projections[$model]->totalCost;
    }

    /**
     * @return list<CostProjection>
     */
    public function cheaperThanCurrent(): array
    {
        $cheaper = [];
        foreach ($this->projections as $projection) {
            if ($projection->totalCost < $this->current->totalCost) {
                $cheaper[] = $projection;
            }
        }

        return $cheaper;
    }
}
