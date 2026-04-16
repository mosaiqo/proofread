<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Diff\CaseDelta;
use Mosaiqo\Proofread\Diff\EvalRunDelta;
use Mosaiqo\Proofread\Diff\EvalRunDiff;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * @param  list<array<string, mixed>>  $resultsData
 */
function seedDiffRun(
    string $datasetName,
    array $resultsData,
    float $durationMs = 10.0,
    ?float $totalCostUsd = null,
): EvalRun {
    $dataset = EvalDataset::query()->firstOrCreate(
        ['name' => $datasetName],
        ['case_count' => count($resultsData), 'checksum' => hash('sha256', $datasetName)],
    );

    $passCount = 0;
    $failCount = 0;
    foreach ($resultsData as $row) {
        if (($row['passed'] ?? true) === true) {
            $passCount++;
        } else {
            $failCount++;
        }
    }

    $run = new EvalRun;
    $run->fill([
        'dataset_id' => $dataset->id,
        'dataset_name' => $datasetName,
        'suite_class' => null,
        'subject_type' => 'unknown',
        'subject_class' => null,
        'commit_sha' => null,
        'model' => null,
        'passed' => $failCount === 0,
        'pass_count' => $passCount,
        'fail_count' => $failCount,
        'error_count' => 0,
        'total_count' => count($resultsData),
        'duration_ms' => $durationMs,
        'total_cost_usd' => $totalCostUsd,
        'total_tokens_in' => null,
        'total_tokens_out' => null,
    ]);
    $run->save();

    foreach ($resultsData as $row) {
        $result = new EvalResult;
        $result->fill([
            'run_id' => $run->id,
            'case_index' => $row['case_index'],
            'case_name' => $row['case_name'] ?? null,
            'input' => $row['input'] ?? ['value' => 'x'],
            'output' => $row['output'] ?? null,
            'expected' => $row['expected'] ?? null,
            'passed' => $row['passed'] ?? true,
            'assertion_results' => $row['assertion_results'] ?? [],
            'error_class' => null,
            'error_message' => null,
            'error_trace' => null,
            'duration_ms' => $row['duration_ms'] ?? 1.0,
            'latency_ms' => null,
            'tokens_in' => null,
            'tokens_out' => null,
            'cost_usd' => $row['cost_usd'] ?? null,
            'model' => null,
        ]);
        $result->save();
    }

    return $run->fresh(['results']) ?? $run;
}

it('computes a diff between two runs of the same dataset', function (): void {
    $base = seedDiffRun('ds-a', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => true],
    ]);
    $head = seedDiffRun('ds-a', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => false],
    ]);

    $delta = (new EvalRunDiff)->compute($base, $head);

    expect($delta)->toBeInstanceOf(EvalRunDelta::class)
        ->and($delta->baseRunId)->toBe($base->id)
        ->and($delta->headRunId)->toBe($head->id)
        ->and($delta->datasetName)->toBe('ds-a')
        ->and($delta->totalCases)->toBe(2);
});

it('rejects runs of different datasets', function (): void {
    $base = seedDiffRun('ds-a', [['case_index' => 0]]);
    $head = seedDiffRun('ds-b', [['case_index' => 0]]);

    expect(fn () => (new EvalRunDiff)->compute($base, $head))
        ->toThrow(InvalidArgumentException::class);
});

it('identifies regressions', function (): void {
    $base = seedDiffRun('ds-reg', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => true],
    ]);
    $head = seedDiffRun('ds-reg', [
        ['case_index' => 0, 'passed' => false],
        ['case_index' => 1, 'passed' => true],
    ]);

    $delta = (new EvalRunDiff)->compute($base, $head);

    expect($delta->regressions)->toBe(1)
        ->and($delta->cases[0])->toBeInstanceOf(CaseDelta::class)
        ->and($delta->cases[0]->status)->toBe('regression')
        ->and($delta->cases[0]->basePassed)->toBeTrue()
        ->and($delta->cases[0]->headPassed)->toBeFalse();
});

it('identifies improvements', function (): void {
    $base = seedDiffRun('ds-imp', [
        ['case_index' => 0, 'passed' => false],
    ]);
    $head = seedDiffRun('ds-imp', [
        ['case_index' => 0, 'passed' => true],
    ]);

    $delta = (new EvalRunDiff)->compute($base, $head);

    expect($delta->improvements)->toBe(1)
        ->and($delta->cases[0]->status)->toBe('improvement');
});

it('identifies stable passes and stable failures', function (): void {
    $base = seedDiffRun('ds-stable', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => false],
    ]);
    $head = seedDiffRun('ds-stable', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => false],
    ]);

    $delta = (new EvalRunDiff)->compute($base, $head);

    expect($delta->stablePasses)->toBe(1)
        ->and($delta->stableFailures)->toBe(1)
        ->and($delta->cases[0]->status)->toBe('stable_pass')
        ->and($delta->cases[1]->status)->toBe('stable_fail');
});

it('handles cases only in base', function (): void {
    $base = seedDiffRun('ds-shrink', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => true],
    ]);
    $head = seedDiffRun('ds-shrink', [
        ['case_index' => 0, 'passed' => true],
    ]);

    $delta = (new EvalRunDiff)->compute($base, $head);

    expect($delta->totalCases)->toBe(2)
        ->and($delta->cases[1]->status)->toBe('base_only')
        ->and($delta->cases[1]->basePassed)->toBeTrue()
        ->and($delta->cases[1]->headPassed)->toBeFalse()
        ->and($delta->cases[1]->headCostUsd)->toBeNull()
        ->and($delta->cases[1]->headDurationMs)->toBeNull();
});

it('handles cases only in head', function (): void {
    $base = seedDiffRun('ds-grow', [
        ['case_index' => 0, 'passed' => true],
    ]);
    $head = seedDiffRun('ds-grow', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => true],
    ]);

    $delta = (new EvalRunDiff)->compute($base, $head);

    expect($delta->totalCases)->toBe(2)
        ->and($delta->cases[1]->status)->toBe('head_only')
        ->and($delta->cases[1]->basePassed)->toBeFalse()
        ->and($delta->cases[1]->headPassed)->toBeTrue()
        ->and($delta->cases[1]->baseCostUsd)->toBeNull()
        ->and($delta->cases[1]->baseDurationMs)->toBeNull();
});

it('lists new failures per case', function (): void {
    $base = seedDiffRun('ds-newf', [
        [
            'case_index' => 0,
            'passed' => true,
            'assertion_results' => [
                ['name' => 'contains', 'passed' => true, 'reason' => null, 'score' => null, 'metadata' => []],
                ['name' => 'length', 'passed' => true, 'reason' => null, 'score' => null, 'metadata' => []],
            ],
        ],
    ]);
    $head = seedDiffRun('ds-newf', [
        [
            'case_index' => 0,
            'passed' => false,
            'assertion_results' => [
                ['name' => 'contains', 'passed' => true, 'reason' => null, 'score' => null, 'metadata' => []],
                ['name' => 'length', 'passed' => false, 'reason' => 'too short', 'score' => null, 'metadata' => []],
            ],
        ],
    ]);

    $delta = (new EvalRunDiff)->compute($base, $head);

    expect($delta->cases[0]->newFailures)->toBe(['length'])
        ->and($delta->cases[0]->fixedFailures)->toBe([]);
});

it('lists fixed failures per case', function (): void {
    $base = seedDiffRun('ds-fixf', [
        [
            'case_index' => 0,
            'passed' => false,
            'assertion_results' => [
                ['name' => 'contains', 'passed' => true, 'reason' => null, 'score' => null, 'metadata' => []],
                ['name' => 'length', 'passed' => false, 'reason' => 'too short', 'score' => null, 'metadata' => []],
            ],
        ],
    ]);
    $head = seedDiffRun('ds-fixf', [
        [
            'case_index' => 0,
            'passed' => true,
            'assertion_results' => [
                ['name' => 'contains', 'passed' => true, 'reason' => null, 'score' => null, 'metadata' => []],
                ['name' => 'length', 'passed' => true, 'reason' => null, 'score' => null, 'metadata' => []],
            ],
        ],
    ]);

    $delta = (new EvalRunDiff)->compute($base, $head);

    expect($delta->cases[0]->fixedFailures)->toBe(['length'])
        ->and($delta->cases[0]->newFailures)->toBe([]);
});

it('computes cost delta sum across cases', function (): void {
    $base = seedDiffRun('ds-cost', [
        ['case_index' => 0, 'passed' => true, 'cost_usd' => 0.01],
        ['case_index' => 1, 'passed' => true, 'cost_usd' => 0.02],
    ]);
    $head = seedDiffRun('ds-cost', [
        ['case_index' => 0, 'passed' => true, 'cost_usd' => 0.05],
        ['case_index' => 1, 'passed' => true, 'cost_usd' => 0.04],
    ]);

    $delta = (new EvalRunDiff)->compute($base, $head);

    expect($delta->costDeltaUsd)->toEqualWithDelta(0.06, 0.0001);
});

it('computes duration delta', function (): void {
    $base = seedDiffRun('ds-dur', [
        ['case_index' => 0, 'passed' => true, 'duration_ms' => 10.0],
        ['case_index' => 1, 'passed' => true, 'duration_ms' => 20.0],
    ]);
    $head = seedDiffRun('ds-dur', [
        ['case_index' => 0, 'passed' => true, 'duration_ms' => 15.0],
        ['case_index' => 1, 'passed' => true, 'duration_ms' => 25.0],
    ]);

    $delta = (new EvalRunDiff)->compute($base, $head);

    expect($delta->durationDeltaMs)->toEqualWithDelta(10.0, 0.0001);
});

it('exposes hasRegressions for CI gating', function (): void {
    $base = seedDiffRun('ds-gate', [
        ['case_index' => 0, 'passed' => true],
    ]);
    $head = seedDiffRun('ds-gate', [
        ['case_index' => 0, 'passed' => false],
    ]);

    $delta = (new EvalRunDiff)->compute($base, $head);

    expect($delta->hasRegressions())->toBeTrue();

    $cleanBase = seedDiffRun('ds-gate-2', [
        ['case_index' => 0, 'passed' => true],
    ]);
    $cleanHead = seedDiffRun('ds-gate-2', [
        ['case_index' => 0, 'passed' => true],
    ]);

    $cleanDelta = (new EvalRunDiff)->compute($cleanBase, $cleanHead);
    expect($cleanDelta->hasRegressions())->toBeFalse();
});

it('preserves case order by index', function (): void {
    $base = seedDiffRun('ds-order', [
        ['case_index' => 2, 'passed' => true],
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => true],
    ]);
    $head = seedDiffRun('ds-order', [
        ['case_index' => 1, 'passed' => true],
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 2, 'passed' => true],
    ]);

    $delta = (new EvalRunDiff)->compute($base, $head);

    expect($delta->cases[0]->caseIndex)->toBe(0)
        ->and($delta->cases[1]->caseIndex)->toBe(1)
        ->and($delta->cases[2]->caseIndex)->toBe(2);
});
