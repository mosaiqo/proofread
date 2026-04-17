<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

uses(RefreshDatabase::class);

function exportRouteDataset(string $name = 'export-route-ds'): EvalDataset
{
    /** @var EvalDataset $dataset */
    $dataset = EvalDataset::query()->create([
        'name' => $name,
        'case_count' => 1,
        'checksum' => null,
    ]);

    return $dataset;
}

function exportRouteRun(?string $datasetName = null): EvalRun
{
    $datasetName = $datasetName ?? 'export-route-ds-'.uniqid();
    $dataset = exportRouteDataset($datasetName);

    /** @var EvalRun $run */
    $run = EvalRun::query()->create([
        'dataset_id' => $dataset->id,
        'dataset_name' => $dataset->name,
        'subject_type' => 'agent',
        'subject_class' => 'App\\Agents\\FooAgent',
        'commit_sha' => 'abc1234',
        'model' => 'claude-sonnet',
        'passed' => true,
        'pass_count' => 1,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 1,
        'duration_ms' => 12.5,
        'total_cost_usd' => null,
        'total_tokens_in' => null,
        'total_tokens_out' => null,
    ]);

    /** @var EvalResult $result */
    $result = EvalResult::query()->create([
        'run_id' => $run->id,
        'case_index' => 0,
        'case_name' => 'exported-case',
        'input' => ['prompt' => 'hello'],
        'output' => 'world',
        'expected' => null,
        'passed' => true,
        'assertion_results' => [
            ['name' => 'contains', 'passed' => true, 'reason' => 'ok', 'metadata' => []],
        ],
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

    expect($result->id)->not->toBeEmpty();

    return $run;
}

it('exports a run as markdown', function (): void {
    $run = exportRouteRun();

    $response = $this->get(route('proofread.runs.export', ['run' => $run->id, 'format' => 'md']));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/markdown');
    expect($response->getContent())->toContain('# Eval Run:');
    expect($response->getContent())->toContain($run->dataset_name);
});

it('exports a run as HTML', function (): void {
    $run = exportRouteRun();

    $response = $this->get(route('proofread.runs.export', ['run' => $run->id, 'format' => 'html']));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/html');
    expect($response->getContent())->toContain('<html');
    expect($response->getContent())->toContain($run->dataset_name);
});

it('sets Content-Disposition attachment on the run export', function (): void {
    $run = exportRouteRun();

    $response = $this->get(route('proofread.runs.export', ['run' => $run->id, 'format' => 'md']));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain("eval-run-{$run->id}.md");
});

it('defaults the run export to markdown when format is missing', function (): void {
    $run = exportRouteRun();

    $response = $this->get(route('proofread.runs.export', ['run' => $run->id]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/markdown');
});

it('rejects invalid formats for the run export', function (): void {
    $run = exportRouteRun();

    $response = $this->get(route('proofread.runs.export', ['run' => $run->id, 'format' => 'pdf']));

    $response->assertStatus(400);
});

it('404s when the run does not exist', function (): void {
    $response = $this->get(route('proofread.runs.export', ['run' => '01JZZZZZZZZZZZZZZZZZZZZZZZ']));

    $response->assertNotFound();
});

it('respects the viewEvals gate on the run export route', function (): void {
    Gate::define('viewEvals', fn ($user = null) => false);

    $run = exportRouteRun();

    $response = $this->get(route('proofread.runs.export', ['run' => $run->id]));

    $response->assertForbidden();
});
