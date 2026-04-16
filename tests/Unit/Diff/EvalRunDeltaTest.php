<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Diff\CaseDelta;
use Mosaiqo\Proofread\Diff\EvalRunDelta;

/**
 * @param  list<CaseDelta>  $cases
 */
function makeEvalRunDelta(
    string $baseRunId = 'base-id',
    string $headRunId = 'head-id',
    string $datasetName = 'ds-a',
    int $totalCases = 2,
    int $regressions = 1,
    int $improvements = 0,
    int $stableFailures = 0,
    int $stablePasses = 1,
    float $costDeltaUsd = 0.05,
    float $durationDeltaMs = 12.5,
    array $cases = [],
): EvalRunDelta {
    if ($cases === []) {
        $cases = [
            new CaseDelta(
                caseIndex: 0,
                caseName: 'case-0',
                basePassed: true,
                headPassed: false,
                status: 'regression',
                baseCostUsd: 0.01,
                headCostUsd: 0.03,
                baseDurationMs: 10.0,
                headDurationMs: 20.0,
                newFailures: ['length'],
                fixedFailures: [],
            ),
            new CaseDelta(
                caseIndex: 1,
                caseName: null,
                basePassed: true,
                headPassed: true,
                status: 'stable_pass',
                baseCostUsd: null,
                headCostUsd: null,
                baseDurationMs: null,
                headDurationMs: null,
                newFailures: [],
                fixedFailures: [],
            ),
        ];
    }

    return new EvalRunDelta(
        baseRunId: $baseRunId,
        headRunId: $headRunId,
        datasetName: $datasetName,
        totalCases: $totalCases,
        regressions: $regressions,
        improvements: $improvements,
        stableFailures: $stableFailures,
        stablePasses: $stablePasses,
        costDeltaUsd: $costDeltaUsd,
        durationDeltaMs: $durationDeltaMs,
        cases: $cases,
    );
}

it('serializes to an array with expected keys', function (): void {
    $delta = makeEvalRunDelta();

    $array = $delta->toArray();

    expect(array_keys($array))->toBe([
        'base_run_id',
        'head_run_id',
        'dataset_name',
        'total_cases',
        'regressions',
        'improvements',
        'stable_passes',
        'stable_failures',
        'cost_delta_usd',
        'duration_delta_ms',
        'has_regressions',
        'cases',
    ]);
});

it('serializes cases via CaseDelta::toArray()', function (): void {
    $delta = makeEvalRunDelta();

    $array = $delta->toArray();

    expect($array['cases'])->toBeArray()->toHaveCount(2)
        ->and($array['cases'][0])->toBeArray()
        ->and(array_keys($array['cases'][0]))->toBe([
            'case_index',
            'case_name',
            'status',
            'base_passed',
            'head_passed',
            'base_cost_usd',
            'head_cost_usd',
            'base_duration_ms',
            'head_duration_ms',
            'new_failures',
            'fixed_failures',
        ])
        ->and($array['cases'][0]['status'])->toBe('regression')
        ->and($array['cases'][1]['status'])->toBe('stable_pass');
});

it('includes has_regressions flag', function (): void {
    $regressing = makeEvalRunDelta(regressions: 3);
    $clean = makeEvalRunDelta(regressions: 0);

    expect($regressing->toArray()['has_regressions'])->toBeTrue()
        ->and($clean->toArray()['has_regressions'])->toBeFalse();
});

it('preserves numeric types', function (): void {
    $delta = makeEvalRunDelta(
        totalCases: 42,
        regressions: 3,
        improvements: 2,
        stableFailures: 4,
        stablePasses: 33,
        costDeltaUsd: 0.1234,
        durationDeltaMs: 56.78,
    );

    $array = $delta->toArray();

    expect($array['total_cases'])->toBeInt()->toBe(42)
        ->and($array['regressions'])->toBeInt()->toBe(3)
        ->and($array['improvements'])->toBeInt()->toBe(2)
        ->and($array['stable_passes'])->toBeInt()->toBe(33)
        ->and($array['stable_failures'])->toBeInt()->toBe(4)
        ->and($array['cost_delta_usd'])->toBeFloat()->toBe(0.1234)
        ->and($array['duration_delta_ms'])->toBeFloat()->toBe(56.78);
});

it('serializes base_run_id, head_run_id and dataset_name as strings', function (): void {
    $delta = makeEvalRunDelta(
        baseRunId: '01HX000000000000000000BASE',
        headRunId: '01HX000000000000000000HEAD',
        datasetName: 'my-dataset',
    );

    $array = $delta->toArray();

    expect($array['base_run_id'])->toBe('01HX000000000000000000BASE')
        ->and($array['head_run_id'])->toBe('01HX000000000000000000HEAD')
        ->and($array['dataset_name'])->toBe('my-dataset');
});
