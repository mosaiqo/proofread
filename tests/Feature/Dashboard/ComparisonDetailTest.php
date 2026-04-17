<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Mosaiqo\Proofread\Http\Livewire\ComparisonDetail;
use Mosaiqo\Proofread\Models\EvalComparison;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

uses(RefreshDatabase::class);

function detailDatasetForComparison(string $name = 'cmp-detail-ds'): EvalDataset
{
    /** @var EvalDataset $dataset */
    $dataset = EvalDataset::query()->create([
        'name' => $name,
        'case_count' => 2,
        'checksum' => null,
    ]);

    return $dataset;
}

/**
 * @param  array<string, mixed>  $attrs
 */
function makeComparisonWithRuns(array $attrs = []): EvalComparison
{
    $datasetName = isset($attrs['dataset_name']) && is_string($attrs['dataset_name'])
        ? $attrs['dataset_name']
        : 'cmp-ds-'.uniqid();

    $dataset = detailDatasetForComparison($datasetName);

    /** @var EvalComparison $comparison */
    $comparison = EvalComparison::query()->create(array_merge([
        'name' => 'compare-models',
        'suite_class' => null,
        'dataset_name' => $dataset->name,
        'dataset_version_id' => null,
        'subject_labels' => ['haiku', 'sonnet'],
        'commit_sha' => 'abc1234',
        'total_runs' => 2,
        'passed_runs' => 1,
        'failed_runs' => 1,
        'total_cost_usd' => 0.25,
        'duration_ms' => 750.5,
    ], $attrs));

    expect($dataset->id)->not->toBeEmpty();

    return $comparison;
}

/**
 * @param  array<string, mixed>  $attrs
 */
function makeComparisonRun(EvalComparison $comparison, string $subjectLabel, array $attrs = []): EvalRun
{
    $dataset = EvalDataset::query()->where('name', $comparison->dataset_name)->firstOrFail();

    /** @var EvalRun $run */
    $run = EvalRun::query()->create(array_merge([
        'dataset_id' => $dataset->id,
        'dataset_name' => $comparison->dataset_name,
        'comparison_id' => $comparison->id,
        'subject_type' => 'agent',
        'subject_class' => 'App\\Agents\\FooAgent',
        'subject_label' => $subjectLabel,
        'commit_sha' => $comparison->commit_sha,
        'model' => $subjectLabel,
        'passed' => true,
        'pass_count' => 2,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 2,
        'duration_ms' => 200.0,
        'total_cost_usd' => 0.125,
        'total_tokens_in' => 50,
        'total_tokens_out' => 25,
    ], $attrs));

    return $run;
}

/**
 * @param  array<string, mixed>  $attrs
 */
function makeComparisonResult(EvalRun $run, int $caseIndex, array $attrs = []): EvalResult
{
    /** @var EvalResult $result */
    $result = EvalResult::query()->create(array_merge([
        'run_id' => $run->id,
        'case_index' => $caseIndex,
        'case_name' => "Case $caseIndex",
        'input' => ['prompt' => "case $caseIndex"],
        'output' => "output $caseIndex",
        'expected' => null,
        'passed' => true,
        'assertion_results' => [
            [
                'name' => 'contains',
                'passed' => true,
                'reason' => 'matched',
                'metadata' => [],
            ],
        ],
        'error_class' => null,
        'error_message' => null,
        'error_trace' => null,
        'duration_ms' => 100.0,
        'latency_ms' => 90.0,
        'tokens_in' => 25,
        'tokens_out' => 13,
        'cost_usd' => 0.0625,
        'model' => $run->model,
        'created_at' => now(),
    ], $attrs));

    return $result;
}

it('renders the comparison detail view', function (): void {
    $comparison = makeComparisonWithRuns();

    Livewire::test(ComparisonDetail::class, ['comparison' => $comparison])
        ->assertOk()
        ->assertSee($comparison->name);
});

it('resolves the comparison by ULID via route binding', function (): void {
    $comparison = makeComparisonWithRuns(['name' => 'route-bind-cmp']);

    $response = $this->get('/evals/comparisons/'.$comparison->id);

    $response->assertOk();
    $response->assertSee('route-bind-cmp');
});

it('404s when the comparison does not exist', function (): void {
    $response = $this->get('/evals/comparisons/01HXXXXXXXXXXXXXXXXXXXXXXX');

    $response->assertNotFound();
});

it('displays the comparison name and dataset in the header', function (): void {
    $comparison = makeComparisonWithRuns([
        'name' => 'my-header-cmp',
        'dataset_name' => 'header-ds',
    ]);

    Livewire::test(ComparisonDetail::class, ['comparison' => $comparison])
        ->assertSee('my-header-cmp')
        ->assertSee('header-ds');
});

it('shows the winner cards with best pass rate cheapest and fastest', function (): void {
    $comparison = makeComparisonWithRuns();

    makeComparisonRun($comparison, 'haiku', [
        'pass_count' => 2,
        'total_count' => 2,
        'duration_ms' => 100.0,
        'total_cost_usd' => 0.10,
    ]);
    makeComparisonRun($comparison, 'sonnet', [
        'pass_count' => 1,
        'total_count' => 2,
        'duration_ms' => 300.0,
        'total_cost_usd' => 0.05,
    ]);

    Livewire::test(ComparisonDetail::class, ['comparison' => $comparison->refresh()])
        ->assertSee('Best pass rate', false)
        ->assertSee('Cheapest', false)
        ->assertSee('Fastest', false);
});

it('renders a matrix with cases as rows and subjects as columns', function (): void {
    $comparison = makeComparisonWithRuns();

    $haiku = makeComparisonRun($comparison, 'haiku');
    makeComparisonResult($haiku, 0, ['case_name' => 'Alpha']);
    makeComparisonResult($haiku, 1, ['case_name' => 'Beta']);

    $sonnet = makeComparisonRun($comparison, 'sonnet');
    makeComparisonResult($sonnet, 0, ['case_name' => 'Alpha']);
    makeComparisonResult($sonnet, 1, ['case_name' => 'Beta']);

    Livewire::test(ComparisonDetail::class, ['comparison' => $comparison->refresh()])
        ->assertSee('matrix-table', false)
        ->assertSee('Alpha')
        ->assertSee('Beta')
        ->assertSee('haiku')
        ->assertSee('sonnet');
});

it('shows pass/fail status in each cell', function (): void {
    $comparison = makeComparisonWithRuns();

    $haiku = makeComparisonRun($comparison, 'haiku');
    makeComparisonResult($haiku, 0, ['passed' => true]);
    makeComparisonResult($haiku, 1, ['passed' => false]);

    $sonnet = makeComparisonRun($comparison, 'sonnet');
    makeComparisonResult($sonnet, 0, ['passed' => true]);
    makeComparisonResult($sonnet, 1, ['passed' => true]);

    Livewire::test(ComparisonDetail::class, ['comparison' => $comparison->refresh()])
        ->assertSee('matrix-cell-pass', false)
        ->assertSee('matrix-cell-fail', false);
});

it('shows aggregate stats in the footer row', function (): void {
    $comparison = makeComparisonWithRuns();

    $haiku = makeComparisonRun($comparison, 'haiku', [
        'pass_count' => 1,
        'fail_count' => 1,
        'total_count' => 2,
        'total_cost_usd' => 0.20,
    ]);
    makeComparisonResult($haiku, 0, ['passed' => true, 'cost_usd' => 0.10, 'latency_ms' => 50.0, 'tokens_in' => 100, 'tokens_out' => 50]);
    makeComparisonResult($haiku, 1, ['passed' => false, 'cost_usd' => 0.10, 'latency_ms' => 150.0, 'tokens_in' => 100, 'tokens_out' => 50]);

    $sonnet = makeComparisonRun($comparison, 'sonnet', [
        'pass_count' => 2,
        'fail_count' => 0,
        'total_count' => 2,
        'total_cost_usd' => 0.40,
    ]);
    makeComparisonResult($sonnet, 0, ['passed' => true, 'cost_usd' => 0.20, 'latency_ms' => 80.0, 'tokens_in' => 100, 'tokens_out' => 50]);
    makeComparisonResult($sonnet, 1, ['passed' => true, 'cost_usd' => 0.20, 'latency_ms' => 120.0, 'tokens_in' => 100, 'tokens_out' => 50]);

    Livewire::test(ComparisonDetail::class, ['comparison' => $comparison->refresh()])
        ->assertSee('matrix-footer-row', false)
        ->assertSee('Pass rate', false)
        ->assertSee('Cost', false)
        ->assertSee('Avg latency', false);
});

it('opens the drawer when a cell is selected', function (): void {
    $comparison = makeComparisonWithRuns();
    $haiku = makeComparisonRun($comparison, 'haiku');
    makeComparisonResult($haiku, 0, ['case_name' => 'Drawer case']);

    Livewire::test(ComparisonDetail::class, ['comparison' => $comparison->refresh()])
        ->call('selectCell', 'haiku', 0)
        ->assertSet('selectedCellId', 'haiku::0')
        ->assertSee('Drawer case');
});

it('closes the drawer via closeCell', function (): void {
    $comparison = makeComparisonWithRuns();
    $haiku = makeComparisonRun($comparison, 'haiku');
    makeComparisonResult($haiku, 0);

    Livewire::test(ComparisonDetail::class, ['comparison' => $comparison->refresh()])
        ->call('selectCell', 'haiku', 0)
        ->assertSet('selectedCellId', 'haiku::0')
        ->call('closeCell')
        ->assertSet('selectedCellId', null);
});

it('shows the case input output and assertions in the drawer', function (): void {
    $comparison = makeComparisonWithRuns();
    $haiku = makeComparisonRun($comparison, 'haiku');
    makeComparisonResult($haiku, 0, [
        'case_name' => 'Inspectable',
        'input' => ['prompt' => 'ping'],
        'output' => 'pong',
        'assertion_results' => [
            [
                'name' => 'contains',
                'passed' => true,
                'reason' => 'matched needle',
                'metadata' => [],
            ],
        ],
    ]);

    Livewire::test(ComparisonDetail::class, ['comparison' => $comparison->refresh()])
        ->call('selectCell', 'haiku', 0)
        ->assertSee('prompt')
        ->assertSee('ping')
        ->assertSee('pong')
        ->assertSee('contains')
        ->assertSee('matched needle');
});

it('shows error details when a case raised', function (): void {
    $comparison = makeComparisonWithRuns();
    $haiku = makeComparisonRun($comparison, 'haiku');
    makeComparisonResult($haiku, 0, [
        'passed' => false,
        'error_class' => 'RuntimeException',
        'error_message' => 'boom',
    ]);

    Livewire::test(ComparisonDetail::class, ['comparison' => $comparison->refresh()])
        ->call('selectCell', 'haiku', 0)
        ->assertSee('RuntimeException')
        ->assertSee('boom');
});

it('links to the full run from the drawer', function (): void {
    $comparison = makeComparisonWithRuns();
    $haiku = makeComparisonRun($comparison, 'haiku');
    makeComparisonResult($haiku, 0);

    Livewire::test(ComparisonDetail::class, ['comparison' => $comparison->refresh()])
        ->call('selectCell', 'haiku', 0)
        ->assertSee('/evals/runs/'.$haiku->id, false);
});

it('respects the viewEvals gate', function (): void {
    Gate::define('viewEvals', fn ($user = null) => false);

    $comparison = makeComparisonWithRuns();

    $response = $this->get('/evals/comparisons/'.$comparison->id);

    $response->assertForbidden();
});

it('handles comparisons with a single subject gracefully', function (): void {
    $comparison = makeComparisonWithRuns([
        'subject_labels' => ['solo'],
        'total_runs' => 1,
        'passed_runs' => 1,
        'failed_runs' => 0,
    ]);

    $solo = makeComparisonRun($comparison, 'solo');
    makeComparisonResult($solo, 0, ['case_name' => 'OnlyCase']);

    Livewire::test(ComparisonDetail::class, ['comparison' => $comparison->refresh()])
        ->assertOk()
        ->assertSee('solo')
        ->assertSee('OnlyCase');
});
