<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Livewire;
use Mosaiqo\Proofread\Http\Livewire\CompareRuns;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

uses(RefreshDatabase::class);

function compareDataset(string $name = 'compare-ds'): EvalDataset
{
    /** @var EvalDataset $dataset */
    $dataset = EvalDataset::query()->create([
        'name' => $name,
        'case_count' => 3,
        'checksum' => null,
    ]);

    return $dataset;
}

/**
 * @param  array<string, mixed>  $attrs
 */
function compareRun(EvalDataset $dataset, array $attrs = []): EvalRun
{
    /** @var EvalRun $run */
    $run = EvalRun::query()->create(array_merge([
        'dataset_id' => $dataset->id,
        'dataset_name' => $dataset->name,
        'subject_type' => 'agent',
        'subject_class' => 'App\\Agents\\FooAgent',
        'commit_sha' => 'abc1234',
        'model' => 'claude-sonnet-4-6',
        'passed' => true,
        'pass_count' => 3,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 3,
        'duration_ms' => 100.0,
        'total_cost_usd' => 0.0100,
        'total_tokens_in' => 100,
        'total_tokens_out' => 50,
    ], $attrs));

    return $run;
}

/**
 * @param  array<string, mixed>  $attrs
 */
function compareResult(EvalRun $run, array $attrs = []): EvalResult
{
    /** @var EvalResult $result */
    $result = EvalResult::query()->create(array_merge([
        'run_id' => $run->id,
        'case_index' => 0,
        'case_name' => 'Case zero',
        'input' => ['prompt' => 'hello'],
        'output' => 'hi there',
        'expected' => ['answer' => 'hi'],
        'passed' => true,
        'assertion_results' => [
            ['name' => 'contains', 'passed' => true, 'reason' => 'ok', 'metadata' => []],
        ],
        'error_class' => null,
        'error_message' => null,
        'error_trace' => null,
        'duration_ms' => 10.0,
        'latency_ms' => 9.0,
        'tokens_in' => 50,
        'tokens_out' => 20,
        'cost_usd' => 0.001,
        'model' => 'claude-sonnet-4-6',
        'created_at' => now(),
    ], $attrs));

    return $result;
}

it('renders the compare view', function (): void {
    Livewire::test(CompareRuns::class)
        ->assertOk()
        ->assertSee('Compare');
});

it('shows a picker when no runs are selected', function (): void {
    $dataset = compareDataset('pick-ds');
    compareRun($dataset);
    compareRun($dataset);

    Livewire::test(CompareRuns::class)
        ->assertSee('Select two runs')
        ->assertSee('pick-ds');
});

it('loads two runs by ULID from query string', function (): void {
    $dataset = compareDataset('query-ds');
    $base = compareRun($dataset);
    $head = compareRun($dataset);

    Livewire::test(CompareRuns::class, ['baseId' => $base->id, 'headId' => $head->id])
        ->assertSee('query-ds')
        ->assertSee($base->id)
        ->assertSee($head->id);
});

it('shows an error when base run does not exist', function (): void {
    $dataset = compareDataset('err-ds');
    $head = compareRun($dataset);

    Livewire::test(CompareRuns::class, [
        'baseId' => '01HXXXXXXXXXXXXXXXXXXXXXXX',
        'headId' => $head->id,
    ])
        ->assertSee('Base run not found');
});

it('shows an error when head run does not exist', function (): void {
    $dataset = compareDataset('err-ds-2');
    $base = compareRun($dataset);

    Livewire::test(CompareRuns::class, [
        'baseId' => $base->id,
        'headId' => '01HXXXXXXXXXXXXXXXXXXXXXXX',
    ])
        ->assertSee('Head run not found');
});

it('shows a warning when runs are of different datasets', function (): void {
    $datasetA = compareDataset('ds-a');
    $datasetB = compareDataset('ds-b');
    $base = compareRun($datasetA);
    $head = compareRun($datasetB);

    Livewire::test(CompareRuns::class, [
        'baseId' => $base->id,
        'headId' => $head->id,
    ])
        ->assertSee('different datasets');
});

it('displays the delta summary when runs are compatible', function (): void {
    $dataset = compareDataset('delta-ds');
    $base = compareRun($dataset, ['pass_count' => 2, 'fail_count' => 1, 'total_count' => 3]);
    $head = compareRun($dataset, ['pass_count' => 1, 'fail_count' => 2, 'total_count' => 3]);

    compareResult($base, ['case_index' => 0, 'passed' => true]);
    compareResult($base, ['case_index' => 1, 'passed' => true]);
    compareResult($base, ['case_index' => 2, 'passed' => false]);

    compareResult($head, ['case_index' => 0, 'passed' => false]);
    compareResult($head, ['case_index' => 1, 'passed' => true]);
    compareResult($head, ['case_index' => 2, 'passed' => false]);

    Livewire::test(CompareRuns::class, [
        'baseId' => $base->id,
        'headId' => $head->id,
    ])
        ->assertSee('Regressions')
        ->assertSee('Improvements')
        ->assertSee('Stable');
});

it('lists regressions first in the cases table', function (): void {
    $dataset = compareDataset('order-ds');
    $base = compareRun($dataset);
    $head = compareRun($dataset);

    // Index 0: stable pass
    compareResult($base, ['case_index' => 0, 'case_name' => 'Stable case', 'passed' => true]);
    compareResult($head, ['case_index' => 0, 'case_name' => 'Stable case', 'passed' => true]);
    // Index 1: regression
    compareResult($base, ['case_index' => 1, 'case_name' => 'Regressing case', 'passed' => true]);
    compareResult($head, ['case_index' => 1, 'case_name' => 'Regressing case', 'passed' => false]);

    Livewire::test(CompareRuns::class, [
        'baseId' => $base->id,
        'headId' => $head->id,
    ])
        ->assertSeeInOrder(['Regressing case', 'Stable case']);
});

it('filters cases by status via statusFilter', function (): void {
    $dataset = compareDataset('filter-ds');
    $base = compareRun($dataset);
    $head = compareRun($dataset);

    compareResult($base, ['case_index' => 0, 'case_name' => 'RegCase', 'passed' => true]);
    compareResult($head, ['case_index' => 0, 'case_name' => 'RegCase', 'passed' => false]);
    compareResult($base, ['case_index' => 1, 'case_name' => 'ImpCase', 'passed' => false]);
    compareResult($head, ['case_index' => 1, 'case_name' => 'ImpCase', 'passed' => true]);

    Livewire::test(CompareRuns::class, [
        'baseId' => $base->id,
        'headId' => $head->id,
    ])
        ->set('statusFilter', 'regression')
        ->assertSee('RegCase')
        ->assertDontSee('ImpCase');
});

it('shows counts per status in filter tabs', function (): void {
    $dataset = compareDataset('counts-ds');
    $base = compareRun($dataset);
    $head = compareRun($dataset);

    compareResult($base, ['case_index' => 0, 'passed' => true]);
    compareResult($head, ['case_index' => 0, 'passed' => false]);

    compareResult($base, ['case_index' => 1, 'passed' => false]);
    compareResult($head, ['case_index' => 1, 'passed' => true]);

    compareResult($base, ['case_index' => 2, 'passed' => true]);
    compareResult($head, ['case_index' => 2, 'passed' => true]);

    Livewire::test(CompareRuns::class, [
        'baseId' => $base->id,
        'headId' => $head->id,
    ])
        ->assertSee('Regressions')
        ->assertSee('Improvements')
        ->assertSee('Stable');
});

it('displays base and head metadata side by side', function (): void {
    $dataset = compareDataset('meta-ds');
    $base = compareRun($dataset, [
        'commit_sha' => 'aaa1111',
        'model' => 'claude-sonnet-4-6',
        'total_cost_usd' => 0.0421,
        'duration_ms' => 1234.0,
    ]);
    $head = compareRun($dataset, [
        'commit_sha' => 'bbb2222',
        'model' => 'claude-opus-4-6',
        'total_cost_usd' => 0.0445,
        'duration_ms' => 1379.0,
    ]);

    Livewire::test(CompareRuns::class, [
        'baseId' => $base->id,
        'headId' => $head->id,
    ])
        ->assertSee('aaa1111')
        ->assertSee('bbb2222')
        ->assertSee('claude-sonnet-4-6')
        ->assertSee('claude-opus-4-6')
        ->assertSee('0.0421')
        ->assertSee('0.0445');
});

it('formats cost and duration deltas with proper sign', function (): void {
    $dataset = compareDataset('signs-ds');
    $base = compareRun($dataset);
    $head = compareRun($dataset);

    compareResult($base, ['case_index' => 0, 'cost_usd' => 0.0100, 'duration_ms' => 100.0]);
    compareResult($head, ['case_index' => 0, 'cost_usd' => 0.0150, 'duration_ms' => 150.0]);

    Livewire::test(CompareRuns::class, [
        'baseId' => $base->id,
        'headId' => $head->id,
    ])
        ->assertSee('+$0.0050')
        ->assertSee('+50');
});

it('exposes base and head IDs in the URL', function (): void {
    $component = new CompareRuns;
    $reflection = new ReflectionClass($component);

    foreach (['baseId', 'headId'] as $property) {
        $attributes = $reflection->getProperty($property)->getAttributes(Url::class);
        expect($attributes)->not->toBeEmpty();
    }
});

it('respects the viewEvals gate', function (): void {
    Gate::define('viewEvals', fn ($user = null) => false);

    $response = $this->get('/evals/compare');

    $response->assertForbidden();
});

it('opens the case drawer on click', function (): void {
    $dataset = compareDataset('drawer-ds');
    $base = compareRun($dataset);
    $head = compareRun($dataset);

    compareResult($base, ['case_index' => 0, 'case_name' => 'Openable case', 'passed' => true]);
    compareResult($head, ['case_index' => 0, 'case_name' => 'Openable case', 'passed' => false]);

    Livewire::test(CompareRuns::class, [
        'baseId' => $base->id,
        'headId' => $head->id,
    ])
        ->call('selectCase', 0)
        ->assertSet('selectedCaseIndex', 0)
        ->assertSee('Openable case');
});

it('handles identical runs gracefully', function (): void {
    $dataset = compareDataset('identical-ds');
    $base = compareRun($dataset);
    $head = compareRun($dataset);

    compareResult($base, ['case_index' => 0, 'passed' => true]);
    compareResult($head, ['case_index' => 0, 'passed' => true]);

    Livewire::test(CompareRuns::class, [
        'baseId' => $base->id,
        'headId' => $head->id,
    ])
        ->assertSee('No changes detected');
});

it('is reachable via the /evals/compare route', function (): void {
    $response = $this->get('/evals/compare');

    $response->assertOk();
});

it('navigates through /evals/compare with query params', function (): void {
    $dataset = compareDataset('nav-ds');
    $base = compareRun($dataset);
    $head = compareRun($dataset);

    $response = $this->get('/evals/compare?base='.$base->id.'&head='.$head->id);

    $response->assertOk();
    $response->assertSee('nav-ds');
});
