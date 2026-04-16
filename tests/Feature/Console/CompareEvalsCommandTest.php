<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * @param  list<array<string, mixed>>  $resultsData
 */
function seedCompareRun(
    string $datasetName,
    array $resultsData,
    float $durationMs = 10.0,
    ?float $totalCostUsd = null,
    ?string $commitSha = null,
    ?string $model = null,
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
        'commit_sha' => $commitSha,
        'model' => $model,
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

it('compares two runs by ULID', function (): void {
    $base = seedCompareRun('ds-cli-ulid', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => true],
    ]);
    $head = seedCompareRun('ds-cli-ulid', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => false],
    ]);

    $exit = Artisan::call('evals:compare', [
        'base' => $base->id,
        'head' => $head->id,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('ds-cli-ulid')
        ->and($output)->toContain($base->id)
        ->and($output)->toContain($head->id);
});

it('compares by short commit sha', function (): void {
    $base = seedCompareRun(
        'ds-cli-sha',
        [['case_index' => 0, 'passed' => true]],
        commitSha: 'abc1234deadbeef',
    );
    $head = seedCompareRun(
        'ds-cli-sha',
        [['case_index' => 0, 'passed' => true]],
        commitSha: 'feed999cafebabe',
    );

    $exit = Artisan::call('evals:compare', [
        'base' => 'abc1',
        'head' => 'feed',
    ]);

    expect($exit)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain($base->id)
        ->and($output)->toContain($head->id);
});

it('resolves latest to the most recent run', function (): void {
    $older = seedCompareRun('ds-cli-latest', [
        ['case_index' => 0, 'passed' => true],
    ]);
    // Ensure ordering is deterministic.
    $older->created_at = now()->subMinute();
    $older->save();

    $newest = seedCompareRun('ds-cli-latest', [
        ['case_index' => 0, 'passed' => true],
    ]);
    $newest->created_at = now();
    $newest->save();

    $exit = Artisan::call('evals:compare', [
        'base' => $older->id,
        'head' => 'latest',
    ]);

    expect($exit)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain($newest->id);
});

it('fails when base run is not found', function (): void {
    $head = seedCompareRun('ds-cli-missing-base', [['case_index' => 0, 'passed' => true]]);

    $exit = Artisan::call('evals:compare', [
        'base' => '01JZZZZZZZZZZZZZZZZZZZZZZZ',
        'head' => $head->id,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('base');
});

it('fails when head run is not found', function (): void {
    $base = seedCompareRun('ds-cli-missing-head', [['case_index' => 0, 'passed' => true]]);

    $exit = Artisan::call('evals:compare', [
        'base' => $base->id,
        'head' => '01JZZZZZZZZZZZZZZZZZZZZZZZ',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('head');
});

it('fails when datasets differ', function (): void {
    $base = seedCompareRun('ds-cli-x', [['case_index' => 0, 'passed' => true]]);
    $head = seedCompareRun('ds-cli-y', [['case_index' => 0, 'passed' => true]]);

    $exit = Artisan::call('evals:compare', [
        'base' => $base->id,
        'head' => $head->id,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('different datasets');
});

it('exits 0 when no regressions', function (): void {
    $base = seedCompareRun('ds-cli-clean', [
        ['case_index' => 0, 'passed' => true],
    ]);
    $head = seedCompareRun('ds-cli-clean', [
        ['case_index' => 0, 'passed' => true],
    ]);

    $exit = Artisan::call('evals:compare', [
        'base' => $base->id,
        'head' => $head->id,
    ]);

    expect($exit)->toBe(0);
});

it('exits 1 when regressions are present', function (): void {
    $base = seedCompareRun('ds-cli-regr', [
        ['case_index' => 0, 'passed' => true],
    ]);
    $head = seedCompareRun('ds-cli-regr', [
        ['case_index' => 0, 'passed' => false],
    ]);

    $exit = Artisan::call('evals:compare', [
        'base' => $base->id,
        'head' => $head->id,
    ]);

    expect($exit)->toBe(1);
});

it('outputs JSON with --format=json', function (): void {
    $base = seedCompareRun('ds-cli-json', [
        ['case_index' => 0, 'passed' => true],
    ]);
    $head = seedCompareRun('ds-cli-json', [
        ['case_index' => 0, 'passed' => false],
    ]);

    Artisan::call('evals:compare', [
        'base' => $base->id,
        'head' => $head->id,
        '--format' => 'json',
    ]);

    $output = Artisan::output();
    $decoded = json_decode($output, true);

    expect($decoded)->toBeArray()
        ->and($decoded['base_run_id'])->toBe($base->id)
        ->and($decoded['head_run_id'])->toBe($head->id)
        ->and($decoded['dataset_name'])->toBe('ds-cli-json')
        ->and($decoded['regressions'])->toBe(1)
        ->and($decoded['has_regressions'])->toBeTrue()
        ->and($decoded['cases'])->toBeArray();
});

it('filters to regressions only with --only-regressions', function (): void {
    $base = seedCompareRun('ds-cli-filter', [
        ['case_index' => 0, 'case_name' => 'alpha', 'passed' => true],
        ['case_index' => 1, 'case_name' => 'beta', 'passed' => true],
        ['case_index' => 2, 'case_name' => 'gamma', 'passed' => false],
    ]);
    $head = seedCompareRun('ds-cli-filter', [
        ['case_index' => 0, 'case_name' => 'alpha', 'passed' => false],
        ['case_index' => 1, 'case_name' => 'beta', 'passed' => true],
        ['case_index' => 2, 'case_name' => 'gamma', 'passed' => false],
    ]);

    Artisan::call('evals:compare', [
        'base' => $base->id,
        'head' => $head->id,
        '--only-regressions' => true,
    ]);

    $output = Artisan::output();

    expect($output)->toContain('alpha')
        ->and($output)->not->toContain('beta')
        ->and($output)->not->toContain('gamma');
});

it('respects --max-cases limit', function (): void {
    $baseCases = [];
    $headCases = [];
    for ($i = 0; $i < 5; $i++) {
        $baseCases[] = ['case_index' => $i, 'case_name' => 'case-'.$i, 'passed' => true];
        // Introduce regressions on all five cases so every one of them is
        // rendered under "Case-level changes" (stable_pass cases are hidden).
        $headCases[] = ['case_index' => $i, 'case_name' => 'case-'.$i, 'passed' => false];
    }

    $base = seedCompareRun('ds-cli-max', $baseCases);
    $head = seedCompareRun('ds-cli-max', $headCases);

    Artisan::call('evals:compare', [
        'base' => $base->id,
        'head' => $head->id,
        '--max-cases' => 2,
    ]);

    $output = Artisan::output();

    expect($output)->toContain('case-0')
        ->and($output)->toContain('case-1')
        ->and($output)->not->toContain('case-3')
        ->and($output)->not->toContain('case-4');
});

it('prints the run metadata in the header', function (): void {
    $base = seedCompareRun(
        'ds-cli-meta',
        [['case_index' => 0, 'passed' => true]],
        model: 'claude-sonnet-4-6',
    );
    $head = seedCompareRun(
        'ds-cli-meta',
        [['case_index' => 0, 'passed' => true]],
        model: 'claude-opus-4-6',
    );

    Artisan::call('evals:compare', [
        'base' => $base->id,
        'head' => $head->id,
    ]);

    $output = Artisan::output();

    expect($output)->toContain('ds-cli-meta')
        ->and($output)->toContain('base:')
        ->and($output)->toContain('head:')
        ->and($output)->toContain('claude-sonnet-4-6')
        ->and($output)->toContain('claude-opus-4-6');
});

it('prints counts summary', function (): void {
    $base = seedCompareRun('ds-cli-counts', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => false],
        ['case_index' => 2, 'passed' => true],
        ['case_index' => 3, 'passed' => false],
    ]);
    $head = seedCompareRun('ds-cli-counts', [
        ['case_index' => 0, 'passed' => false],
        ['case_index' => 1, 'passed' => true],
        ['case_index' => 2, 'passed' => true],
        ['case_index' => 3, 'passed' => false],
    ]);

    Artisan::call('evals:compare', [
        'base' => $base->id,
        'head' => $head->id,
    ]);

    $output = Artisan::output();

    expect($output)->toContain('Regressions')
        ->and($output)->toContain('Improvements')
        ->and($output)->toContain('Stable passes')
        ->and($output)->toContain('Stable failures')
        ->and($output)->toContain('Total cases');
});

it('prints cost and duration deltas with proper sign', function (): void {
    $base = seedCompareRun('ds-cli-deltas', [
        ['case_index' => 0, 'passed' => true, 'cost_usd' => 0.01, 'duration_ms' => 10.0],
    ]);
    $head = seedCompareRun('ds-cli-deltas', [
        ['case_index' => 0, 'passed' => true, 'cost_usd' => 0.03, 'duration_ms' => 25.0],
    ]);

    Artisan::call('evals:compare', [
        'base' => $base->id,
        'head' => $head->id,
    ]);

    $output = Artisan::output();

    expect($output)->toContain('Cost delta')
        ->and($output)->toContain('+$')
        ->and($output)->toContain('Duration delta')
        ->and($output)->toMatch('/\+\d+(\.\d+)?ms/');
});

it('reports no changes when runs are identical', function (): void {
    $base = seedCompareRun('ds-cli-identical', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => true],
    ]);
    $head = seedCompareRun('ds-cli-identical', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => true],
    ]);

    $exit = Artisan::call('evals:compare', [
        'base' => $base->id,
        'head' => $head->id,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('No differences detected');
});

it('prints a negative sign for negative cost and duration deltas', function (): void {
    $base = seedCompareRun('ds-cli-negdelta', [
        ['case_index' => 0, 'passed' => true, 'cost_usd' => 0.05, 'duration_ms' => 100.0],
    ]);
    $head = seedCompareRun('ds-cli-negdelta', [
        ['case_index' => 0, 'passed' => true, 'cost_usd' => 0.01, 'duration_ms' => 20.0],
    ]);

    Artisan::call('evals:compare', [
        'base' => $base->id,
        'head' => $head->id,
    ]);

    $output = Artisan::output();

    expect($output)->toContain('-$')
        ->and($output)->toMatch('/-\d+(\.\d+)?ms/');
});
