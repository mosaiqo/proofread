<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Diff\CaseDelta;

/**
 * @param  list<string>  $newFailures
 * @param  list<string>  $fixedFailures
 */
function makeCaseDelta(
    int $caseIndex = 0,
    ?string $caseName = 'case-a',
    bool $basePassed = true,
    bool $headPassed = false,
    string $status = 'regression',
    ?float $baseCostUsd = 0.01,
    ?float $headCostUsd = 0.03,
    ?float $baseDurationMs = 10.0,
    ?float $headDurationMs = 20.0,
    array $newFailures = ['length'],
    array $fixedFailures = [],
): CaseDelta {
    return new CaseDelta(
        caseIndex: $caseIndex,
        caseName: $caseName,
        basePassed: $basePassed,
        headPassed: $headPassed,
        status: $status,
        baseCostUsd: $baseCostUsd,
        headCostUsd: $headCostUsd,
        baseDurationMs: $baseDurationMs,
        headDurationMs: $headDurationMs,
        newFailures: $newFailures,
        fixedFailures: $fixedFailures,
    );
}

it('serializes to an array with expected keys', function (): void {
    $case = makeCaseDelta();

    $array = $case->toArray();

    expect(array_keys($array))->toBe([
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
    ]);
});

it('handles null cost and duration fields', function (): void {
    $case = makeCaseDelta(
        baseCostUsd: null,
        headCostUsd: null,
        baseDurationMs: null,
        headDurationMs: null,
    );

    $array = $case->toArray();

    expect($array['base_cost_usd'])->toBeNull()
        ->and($array['head_cost_usd'])->toBeNull()
        ->and($array['base_duration_ms'])->toBeNull()
        ->and($array['head_duration_ms'])->toBeNull();
});

it('includes new_failures and fixed_failures', function (): void {
    $case = makeCaseDelta(
        newFailures: ['length', 'contains'],
        fixedFailures: ['regex'],
    );

    $array = $case->toArray();

    expect($array['new_failures'])->toBe(['length', 'contains'])
        ->and($array['fixed_failures'])->toBe(['regex']);
});

it('preserves primitive types in CaseDelta array shape', function (): void {
    $case = makeCaseDelta(
        caseIndex: 5,
        caseName: 'named-case',
        basePassed: true,
        headPassed: false,
        status: 'regression',
        baseCostUsd: 0.25,
        headCostUsd: 0.75,
        baseDurationMs: 100.5,
        headDurationMs: 200.25,
    );

    $array = $case->toArray();

    expect($array['case_index'])->toBeInt()->toBe(5)
        ->and($array['case_name'])->toBeString()->toBe('named-case')
        ->and($array['status'])->toBeString()->toBe('regression')
        ->and($array['base_passed'])->toBeBool()->toBeTrue()
        ->and($array['head_passed'])->toBeBool()->toBeFalse()
        ->and($array['base_cost_usd'])->toBeFloat()->toBe(0.25)
        ->and($array['head_cost_usd'])->toBeFloat()->toBe(0.75)
        ->and($array['base_duration_ms'])->toBeFloat()->toBe(100.5)
        ->and($array['head_duration_ms'])->toBeFloat()->toBe(200.25);
});
