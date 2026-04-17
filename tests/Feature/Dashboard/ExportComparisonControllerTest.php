<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Mosaiqo\Proofread\Models\EvalComparison;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

uses(RefreshDatabase::class);

function exportRouteComparison(?string $datasetName = null): EvalComparison
{
    $datasetName = $datasetName ?? 'export-cmp-ds-'.uniqid();

    /** @var EvalDataset $dataset */
    $dataset = EvalDataset::query()->create([
        'name' => $datasetName,
        'case_count' => 1,
        'checksum' => null,
    ]);

    /** @var EvalComparison $comparison */
    $comparison = EvalComparison::query()->create([
        'name' => 'route-comparison',
        'suite_class' => null,
        'dataset_name' => $dataset->name,
        'dataset_version_id' => null,
        'subject_labels' => ['haiku', 'sonnet'],
        'commit_sha' => null,
        'total_runs' => 2,
        'passed_runs' => 2,
        'failed_runs' => 0,
        'total_cost_usd' => 0.01,
        'duration_ms' => 100.0,
    ]);

    foreach (['haiku', 'sonnet'] as $label) {
        /** @var EvalRun $run */
        $run = EvalRun::query()->create([
            'dataset_id' => $dataset->id,
            'dataset_name' => $dataset->name,
            'comparison_id' => $comparison->id,
            'subject_type' => 'agent',
            'subject_class' => 'App\\Agents\\FooAgent',
            'subject_label' => $label,
            'commit_sha' => null,
            'model' => $label,
            'passed' => true,
            'pass_count' => 1,
            'fail_count' => 0,
            'error_count' => 0,
            'total_count' => 1,
            'duration_ms' => 50.0,
            'total_cost_usd' => 0.005,
            'total_tokens_in' => 10,
            'total_tokens_out' => 5,
        ]);

        EvalResult::query()->create([
            'run_id' => $run->id,
            'case_index' => 0,
            'case_name' => 'cmp-case',
            'input' => ['prompt' => 'p'],
            'output' => 'o',
            'expected' => null,
            'passed' => true,
            'assertion_results' => [],
            'error_class' => null,
            'error_message' => null,
            'error_trace' => null,
            'duration_ms' => 1.0,
            'latency_ms' => null,
            'tokens_in' => null,
            'tokens_out' => null,
            'cost_usd' => null,
            'model' => null,
        ]);
    }

    return $comparison;
}

it('exports a comparison as markdown', function (): void {
    $comparison = exportRouteComparison();

    $response = $this->get(route('proofread.comparisons.export', ['comparison' => $comparison->id, 'format' => 'md']));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/markdown');
    expect($response->getContent())->toContain($comparison->dataset_name);
});

it('exports a comparison as HTML', function (): void {
    $comparison = exportRouteComparison();

    $response = $this->get(route('proofread.comparisons.export', ['comparison' => $comparison->id, 'format' => 'html']));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/html');
    expect($response->getContent())->toContain('<html');
});

it('sets Content-Disposition attachment on the comparison export', function (): void {
    $comparison = exportRouteComparison();

    $response = $this->get(route('proofread.comparisons.export', ['comparison' => $comparison->id, 'format' => 'md']));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain("eval-comparison-{$comparison->id}.md");
});

it('defaults the comparison export to markdown when format is missing', function (): void {
    $comparison = exportRouteComparison();

    $response = $this->get(route('proofread.comparisons.export', ['comparison' => $comparison->id]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/markdown');
});

it('rejects invalid formats for the comparison export', function (): void {
    $comparison = exportRouteComparison();

    $response = $this->get(route('proofread.comparisons.export', ['comparison' => $comparison->id, 'format' => 'pdf']));

    $response->assertStatus(400);
});

it('404s when the comparison does not exist', function (): void {
    $response = $this->get(route('proofread.comparisons.export', ['comparison' => '01JZZZZZZZZZZZZZZZZZZZZZZZ']));

    $response->assertNotFound();
});

it('respects the viewEvals gate on the comparison export route', function (): void {
    Gate::define('viewEvals', fn ($user = null) => false);

    $comparison = exportRouteComparison();

    $response = $this->get(route('proofread.comparisons.export', ['comparison' => $comparison->id]));

    $response->assertForbidden();
});
