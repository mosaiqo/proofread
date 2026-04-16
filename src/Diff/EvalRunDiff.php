<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Diff;

use InvalidArgumentException;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * Computes a structured diff between two persisted EvalRun models.
 *
 * Both runs must target the same dataset (matched by name). The returned
 * EvalRunDelta classifies each case as regression, improvement, stable pass,
 * stable fail, base-only, or head-only, and aggregates cost and duration
 * differences across the run.
 */
final class EvalRunDiff
{
    public function compute(EvalRun $base, EvalRun $head): EvalRunDelta
    {
        if ($base->dataset_name !== $head->dataset_name) {
            throw new InvalidArgumentException(sprintf(
                'Cannot diff runs from different datasets: base=%s, head=%s.',
                $base->dataset_name,
                $head->dataset_name,
            ));
        }

        $baseResults = $this->indexResults($base);
        $headResults = $this->indexResults($head);

        $indices = array_values(array_unique([
            ...array_keys($baseResults),
            ...array_keys($headResults),
        ]));
        sort($indices);

        $cases = [];
        $regressions = 0;
        $improvements = 0;
        $stablePasses = 0;
        $stableFailures = 0;
        $costDelta = 0.0;
        $durationDelta = 0.0;

        foreach ($indices as $index) {
            $baseResult = $baseResults[$index] ?? null;
            $headResult = $headResults[$index] ?? null;

            $delta = $this->buildCaseDelta($index, $baseResult, $headResult);
            $cases[] = $delta;

            switch ($delta->status) {
                case 'regression':
                    $regressions++;
                    break;
                case 'improvement':
                    $improvements++;
                    break;
                case 'stable_pass':
                    $stablePasses++;
                    break;
                case 'stable_fail':
                    $stableFailures++;
                    break;
            }

            if ($delta->baseCostUsd !== null && $delta->headCostUsd !== null) {
                $costDelta += $delta->headCostUsd - $delta->baseCostUsd;
            }

            if ($delta->baseDurationMs !== null && $delta->headDurationMs !== null) {
                $durationDelta += $delta->headDurationMs - $delta->baseDurationMs;
            }
        }

        return new EvalRunDelta(
            baseRunId: $base->id,
            headRunId: $head->id,
            datasetName: $base->dataset_name,
            totalCases: count($cases),
            regressions: $regressions,
            improvements: $improvements,
            stableFailures: $stableFailures,
            stablePasses: $stablePasses,
            costDeltaUsd: $costDelta,
            durationDeltaMs: $durationDelta,
            cases: $cases,
        );
    }

    /**
     * @return array<int, EvalResult>
     */
    private function indexResults(EvalRun $run): array
    {
        $indexed = [];
        foreach ($run->results as $result) {
            $indexed[$result->case_index] = $result;
        }

        return $indexed;
    }

    private function buildCaseDelta(int $index, ?EvalResult $base, ?EvalResult $head): CaseDelta
    {
        if ($base !== null && $head === null) {
            return new CaseDelta(
                caseIndex: $index,
                caseName: $base->case_name,
                basePassed: $base->passed,
                headPassed: false,
                status: 'base_only',
                baseCostUsd: $base->cost_usd,
                headCostUsd: null,
                baseDurationMs: $base->duration_ms,
                headDurationMs: null,
                newFailures: [],
                fixedFailures: [],
            );
        }

        if ($base === null && $head !== null) {
            return new CaseDelta(
                caseIndex: $index,
                caseName: $head->case_name,
                basePassed: false,
                headPassed: $head->passed,
                status: 'head_only',
                baseCostUsd: null,
                headCostUsd: $head->cost_usd,
                baseDurationMs: null,
                headDurationMs: $head->duration_ms,
                newFailures: [],
                fixedFailures: [],
            );
        }

        /** @var EvalResult $base */
        /** @var EvalResult $head */
        $basePassed = $base->passed;
        $headPassed = $head->passed;
        $status = match (true) {
            $basePassed && ! $headPassed => 'regression',
            ! $basePassed && $headPassed => 'improvement',
            $basePassed => 'stable_pass',
            default => 'stable_fail',
        };

        [$newFailures, $fixedFailures] = $this->diffAssertions(
            $base->assertion_results,
            $head->assertion_results,
        );

        return new CaseDelta(
            caseIndex: $index,
            caseName: $head->case_name ?? $base->case_name,
            basePassed: $basePassed,
            headPassed: $headPassed,
            status: $status,
            baseCostUsd: $base->cost_usd,
            headCostUsd: $head->cost_usd,
            baseDurationMs: $base->duration_ms,
            headDurationMs: $head->duration_ms,
            newFailures: $newFailures,
            fixedFailures: $fixedFailures,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $baseAssertions
     * @param  list<array<string, mixed>>  $headAssertions
     * @return array{0: list<string>, 1: list<string>}
     */
    private function diffAssertions(array $baseAssertions, array $headAssertions): array
    {
        $baseByName = $this->indexAssertionsByName($baseAssertions);
        $headByName = $this->indexAssertionsByName($headAssertions);

        $newFailures = [];
        $fixedFailures = [];

        foreach ($headByName as $name => $passed) {
            $basePassed = $baseByName[$name] ?? null;
            if ($basePassed === true && $passed === false) {
                $newFailures[] = $name;
            }
        }

        foreach ($headByName as $name => $passed) {
            $basePassed = $baseByName[$name] ?? null;
            if ($basePassed === false && $passed === true) {
                $fixedFailures[] = $name;
            }
        }

        return [$newFailures, $fixedFailures];
    }

    /**
     * @param  list<array<string, mixed>>  $assertions
     * @return array<string, bool>
     */
    private function indexAssertionsByName(array $assertions): array
    {
        $out = [];
        foreach ($assertions as $assertion) {
            $name = $assertion['name'] ?? null;
            $passed = $assertion['passed'] ?? null;
            if (is_string($name) && $name !== '' && is_bool($passed)) {
                $out[$name] = $passed;
            }
        }

        return $out;
    }
}
