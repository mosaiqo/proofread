<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mosaiqo\Proofread\Mcp\Tools\GetEvalRunDiffTool;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;
use Mosaiqo\Proofread\Tests\Fixtures\Mcp\ProofreadMcpServer;

uses(RefreshDatabase::class);

/**
 * @param  list<array<string, mixed>>  $resultsData
 */
function seedRunForDiffTool(
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

    return $run;
}

/**
 * @return array<string, mixed>
 */
function callDiffTool(string $baseId, string $headId): array
{
    $response = ProofreadMcpServer::tool(GetEvalRunDiffTool::class, [
        'base_run_id' => $baseId,
        'head_run_id' => $headId,
    ]);

    $payload = (fn () => $this->response->toArray())->call($response);
    /** @var array<string, mixed> $structured */
    $structured = $payload['result']['structuredContent'] ?? [];

    return $structured;
}

it('computes a diff between two runs of the same dataset', function (): void {
    $base = seedRunForDiffTool('tool-ds-a', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => true],
    ]);
    $head = seedRunForDiffTool('tool-ds-a', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => false],
    ]);

    $structured = callDiffTool($base->id, $head->id);

    expect($structured['base_run_id'])->toBe($base->id)
        ->and($structured['head_run_id'])->toBe($head->id)
        ->and($structured['dataset_name'])->toBe('tool-ds-a')
        ->and($structured['total_cases'])->toBe(2);
});

it('returns an error when base_run_id does not exist', function (): void {
    $head = seedRunForDiffTool('tool-ds-missing', [['case_index' => 0]]);

    $response = ProofreadMcpServer::tool(GetEvalRunDiffTool::class, [
        'base_run_id' => '01JZZZZZZZZZZZZZZZZZZZZZZZ',
        'head_run_id' => $head->id,
    ]);

    $response->assertHasErrors();
});

it('returns an error when head_run_id does not exist', function (): void {
    $base = seedRunForDiffTool('tool-ds-missing-head', [['case_index' => 0]]);

    $response = ProofreadMcpServer::tool(GetEvalRunDiffTool::class, [
        'base_run_id' => $base->id,
        'head_run_id' => '01JZZZZZZZZZZZZZZZZZZZZZZZ',
    ]);

    $response->assertHasErrors();
});

it('returns an error when runs are from different datasets', function (): void {
    $base = seedRunForDiffTool('tool-ds-x', [['case_index' => 0]]);
    $head = seedRunForDiffTool('tool-ds-y', [['case_index' => 0]]);

    $response = ProofreadMcpServer::tool(GetEvalRunDiffTool::class, [
        'base_run_id' => $base->id,
        'head_run_id' => $head->id,
    ]);

    $response->assertHasErrors(['different datasets']);
});

it('includes counts for regressions improvements stable passes and stable failures', function (): void {
    $base = seedRunForDiffTool('tool-ds-counts', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => false],
        ['case_index' => 2, 'passed' => true],
        ['case_index' => 3, 'passed' => false],
    ]);
    $head = seedRunForDiffTool('tool-ds-counts', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => true],
        ['case_index' => 2, 'passed' => false],
        ['case_index' => 3, 'passed' => false],
    ]);

    $structured = callDiffTool($base->id, $head->id);

    expect($structured['regressions'])->toBe(1)
        ->and($structured['improvements'])->toBe(1)
        ->and($structured['stable_passes'])->toBe(1)
        ->and($structured['stable_failures'])->toBe(1);
});

it('serializes each CaseDelta with all expected fields', function (): void {
    $base = seedRunForDiffTool('tool-ds-fields', [
        ['case_index' => 0, 'case_name' => 'first', 'passed' => true, 'cost_usd' => 0.01, 'duration_ms' => 12.0],
    ]);
    $head = seedRunForDiffTool('tool-ds-fields', [
        ['case_index' => 0, 'case_name' => 'first', 'passed' => false, 'cost_usd' => 0.02, 'duration_ms' => 15.0],
    ]);

    $structured = callDiffTool($base->id, $head->id);

    expect($structured['cases'])->toHaveCount(1);
    $case = $structured['cases'][0];
    expect($case)->toHaveKeys([
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
        ->and($case['case_index'])->toBe(0)
        ->and($case['case_name'])->toBe('first')
        ->and($case['status'])->toBe('regression')
        ->and($case['base_passed'])->toBeTrue()
        ->and($case['head_passed'])->toBeFalse()
        ->and($case['base_cost_usd'])->toEqualWithDelta(0.01, 0.0001)
        ->and($case['head_cost_usd'])->toEqualWithDelta(0.02, 0.0001)
        ->and($case['base_duration_ms'])->toEqualWithDelta(12.0, 0.0001)
        ->and($case['head_duration_ms'])->toEqualWithDelta(15.0, 0.0001);
});

it('lists new_failures and fixed_failures per case', function (): void {
    $base = seedRunForDiffTool('tool-ds-failures', [
        [
            'case_index' => 0,
            'passed' => true,
            'assertion_results' => [
                ['name' => 'contains', 'passed' => true, 'reason' => null, 'score' => null, 'metadata' => []],
                ['name' => 'length', 'passed' => true, 'reason' => null, 'score' => null, 'metadata' => []],
            ],
        ],
    ]);
    $head = seedRunForDiffTool('tool-ds-failures', [
        [
            'case_index' => 0,
            'passed' => false,
            'assertion_results' => [
                ['name' => 'contains', 'passed' => true, 'reason' => null, 'score' => null, 'metadata' => []],
                ['name' => 'length', 'passed' => false, 'reason' => 'too short', 'score' => null, 'metadata' => []],
            ],
        ],
    ]);

    $structured = callDiffTool($base->id, $head->id);

    expect($structured['cases'][0]['new_failures'])->toBe(['length'])
        ->and($structured['cases'][0]['fixed_failures'])->toBe([]);
});

it('includes cost_delta_usd and duration_delta_ms', function (): void {
    $base = seedRunForDiffTool('tool-ds-deltas', [
        ['case_index' => 0, 'passed' => true, 'cost_usd' => 0.01, 'duration_ms' => 10.0],
        ['case_index' => 1, 'passed' => true, 'cost_usd' => 0.02, 'duration_ms' => 20.0],
    ]);
    $head = seedRunForDiffTool('tool-ds-deltas', [
        ['case_index' => 0, 'passed' => true, 'cost_usd' => 0.05, 'duration_ms' => 15.0],
        ['case_index' => 1, 'passed' => true, 'cost_usd' => 0.04, 'duration_ms' => 25.0],
    ]);

    $structured = callDiffTool($base->id, $head->id);

    expect($structured['cost_delta_usd'])->toEqualWithDelta(0.06, 0.0001)
        ->and($structured['duration_delta_ms'])->toEqualWithDelta(10.0, 0.0001);
});

it('exposes has_regressions true when there are regressions', function (): void {
    $base = seedRunForDiffTool('tool-ds-regr-true', [
        ['case_index' => 0, 'passed' => true],
    ]);
    $head = seedRunForDiffTool('tool-ds-regr-true', [
        ['case_index' => 0, 'passed' => false],
    ]);

    $structured = callDiffTool($base->id, $head->id);

    expect($structured['has_regressions'])->toBeTrue();
});

it('exposes has_regressions false when all regressions are resolved', function (): void {
    $base = seedRunForDiffTool('tool-ds-regr-false', [
        ['case_index' => 0, 'passed' => false],
    ]);
    $head = seedRunForDiffTool('tool-ds-regr-false', [
        ['case_index' => 0, 'passed' => true],
    ]);

    $structured = callDiffTool($base->id, $head->id);

    expect($structured['has_regressions'])->toBeFalse();
});

it('truncates case list when exceeding 50 but keeps all regressions', function (): void {
    $baseCases = [];
    $headCases = [];

    for ($i = 0; $i < 5; $i++) {
        $baseCases[] = ['case_index' => $i, 'passed' => true];
        $headCases[] = ['case_index' => $i, 'passed' => false];
    }

    for ($i = 5; $i < 60; $i++) {
        $baseCases[] = ['case_index' => $i, 'passed' => true];
        $headCases[] = ['case_index' => $i, 'passed' => true];
    }

    $base = seedRunForDiffTool('tool-ds-truncate', $baseCases);
    $head = seedRunForDiffTool('tool-ds-truncate', $headCases);

    $structured = callDiffTool($base->id, $head->id);

    expect($structured['total_cases'])->toBe(60)
        ->and($structured['regressions'])->toBe(5)
        ->and($structured['cases_truncated'])->toBeTrue()
        ->and($structured['cases_omitted'])->toBe(10)
        ->and($structured['cases'])->toHaveCount(50);

    $regressionCases = array_filter(
        $structured['cases'],
        static fn (array $case): bool => $case['status'] === 'regression',
    );
    expect($regressionCases)->toHaveCount(5);
});
