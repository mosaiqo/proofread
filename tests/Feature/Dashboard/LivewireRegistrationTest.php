<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\ComponentRegistry;
use Mosaiqo\Proofread\Http\Livewire\CompareRuns;
use Mosaiqo\Proofread\Http\Livewire\CostsBreakdown;
use Mosaiqo\Proofread\Http\Livewire\DatasetsList;
use Mosaiqo\Proofread\Http\Livewire\Overview;
use Mosaiqo\Proofread\Http\Livewire\RunDetail;
use Mosaiqo\Proofread\Http\Livewire\RunsList;
use Mosaiqo\Proofread\Http\Livewire\ShadowPanel;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalRun;

uses(RefreshDatabase::class);

dataset('livewire_components', [
    'overview' => ['proofread::overview', Overview::class],
    'runs-list' => ['proofread::runs-list', RunsList::class],
    'run-detail' => ['proofread::run-detail', RunDetail::class],
    'datasets-list' => ['proofread::datasets-list', DatasetsList::class],
    'compare-runs' => ['proofread::compare-runs', CompareRuns::class],
    'costs-breakdown' => ['proofread::costs-breakdown', CostsBreakdown::class],
    'shadow-panel' => ['proofread::shadow-panel', ShadowPanel::class],
]);

it('resolves the dashboard component class from its alias', function (string $alias, string $class): void {
    $registry = app(ComponentRegistry::class);

    expect($registry->getClass($alias))->toBe($class);
})->with('livewire_components');

it('resolves the registered alias from the component class', function (string $alias, string $class): void {
    $registry = app(ComponentRegistry::class);

    expect($registry->getName($class))->toBe($alias);
})->with('livewire_components');

it('emits the registered alias in the wire:snapshot payload when rendering RunDetail', function (): void {
    /** @var EvalDataset $dataset */
    $dataset = EvalDataset::query()->create([
        'name' => 'snapshot-ds',
        'case_count' => 1,
        'checksum' => null,
    ]);

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
        'duration_ms' => 10.0,
        'total_cost_usd' => 0.001,
        'total_tokens_in' => 10,
        'total_tokens_out' => 5,
    ]);

    $response = $this->get('/evals/runs/'.$run->id);

    $response->assertOk();
    expect($response->getContent())->toContain('proofread::run-detail');
});
