<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Simulation;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Pricing\PricingTable;

/**
 * Simulate the cost of running an agent's historical traffic under different
 * models. Takes the persisted ShadowCapture rows within a window, computes
 * the cost under the captures' actual model (the "current" projection) and
 * under a set of alternative models using the configured PricingTable.
 *
 * The current model is chosen as the mode (most frequent model_used value)
 * across the matched captures. Ties are broken by the most recently captured
 * occurrence, which matches the intuition that a recent model migration is
 * the one to reason about when asking "what if I switch?".
 *
 * Captures that are missing both tokens_in and tokens_out are considered
 * unusable and counted as skipped for every projection.
 */
final class CostSimulator
{
    public function __construct(
        private readonly PricingTable $pricing,
    ) {}

    /**
     * @param  list<string>  $alternativeModels  Models to simulate against. If empty, all pricing-table models except the current are used.
     */
    public function simulate(
        string $agentClass,
        DateTimeInterface $from,
        DateTimeInterface $to,
        array $alternativeModels = [],
    ): CostSimulationReport {
        $fromImmutable = DateTimeImmutable::createFromInterface($from);
        $toImmutable = DateTimeImmutable::createFromInterface($to);

        /** @var Collection<int, ShadowCapture> $captures */
        $captures = ShadowCapture::query()
            ->where('agent_class', $agentClass)
            ->where('captured_at', '>=', $fromImmutable)
            ->where('captured_at', '<=', $toImmutable)
            ->orderBy('captured_at', 'asc')
            ->get();

        $totalCaptures = $captures->count();

        if ($totalCaptures === 0) {
            return new CostSimulationReport(
                agentClass: $agentClass,
                current: new CostProjection('', 0.0, 0.0, 0, 0),
                projections: [],
                totalCaptures: 0,
                from: $fromImmutable,
                to: $toImmutable,
            );
        }

        $currentModel = $this->pickCurrentModel($captures);
        $alternatives = $alternativeModels !== []
            ? $alternativeModels
            : array_values(array_filter(
                array_keys($this->pricing->all()),
                static fn (string $model): bool => $model !== $currentModel,
            ));

        $currentProjection = $this->project($currentModel, $captures);

        $projections = [];
        foreach ($alternatives as $model) {
            if ($model === $currentModel) {
                continue;
            }
            $projections[$model] = $this->project($model, $captures);
        }

        return new CostSimulationReport(
            agentClass: $agentClass,
            current: $currentProjection,
            projections: $projections,
            totalCaptures: $totalCaptures,
            from: $fromImmutable,
            to: $toImmutable,
        );
    }

    /**
     * @param  Collection<int, ShadowCapture>  $captures
     */
    private function project(string $model, Collection $captures): CostProjection
    {
        $total = 0.0;
        $covered = 0;
        $skipped = 0;

        foreach ($captures as $capture) {
            if ($capture->tokens_in === null && $capture->tokens_out === null) {
                $skipped++;

                continue;
            }

            $cost = $this->pricing->cost(
                $model,
                $capture->tokens_in ?? 0,
                $capture->tokens_out ?? 0,
            );

            if ($cost === null) {
                $skipped++;

                continue;
            }

            $total += $cost;
            $covered++;
        }

        $perCapture = $covered > 0 ? $total / $covered : 0.0;

        return new CostProjection(
            model: $model,
            totalCost: round($total, 6),
            perCaptureCost: round($perCapture, 6),
            coveredCaptures: $covered,
            skippedCaptures: $skipped,
        );
    }

    /**
     * @param  Collection<int, ShadowCapture>  $captures
     */
    private function pickCurrentModel(Collection $captures): string
    {
        $counts = [];
        $lastSeen = [];

        foreach ($captures as $capture) {
            $model = $capture->model_used;
            if (! is_string($model) || $model === '') {
                continue;
            }

            $counts[$model] = ($counts[$model] ?? 0) + 1;
            $lastSeen[$model] = $capture->captured_at->getTimestamp();
        }

        if ($counts === []) {
            return '';
        }

        arsort($counts);
        $maxCount = (int) array_values($counts)[0];

        $tied = array_keys(array_filter(
            $counts,
            static fn (int $count): bool => $count === $maxCount,
        ));

        if (count($tied) === 1) {
            return $tied[0];
        }

        $winner = $tied[0];
        foreach ($tied as $model) {
            if (($lastSeen[$model] ?? 0) > ($lastSeen[$winner] ?? 0)) {
                $winner = $model;
            }
        }

        return $winner;
    }
}
