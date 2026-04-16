<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Mosaiqo\Proofread\Http\Livewire\DatasetsList;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalRun;

uses(RefreshDatabase::class);

function explorerDataset(string $name, int $caseCount = 3): EvalDataset
{
    /** @var EvalDataset $dataset */
    $dataset = EvalDataset::query()->create([
        'name' => $name,
        'case_count' => $caseCount,
        'checksum' => null,
    ]);

    return $dataset;
}

/**
 * @param  array<string, mixed>  $attrs
 */
function explorerRun(EvalDataset $dataset, array $attrs = []): EvalRun
{
    /** @var EvalRun $run */
    $run = EvalRun::query()->create(array_merge([
        'dataset_id' => $dataset->id,
        'dataset_name' => $dataset->name,
        'subject_type' => 'agent',
        'passed' => true,
        'pass_count' => 3,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 3,
        'duration_ms' => 100.0,
        'total_cost_usd' => 0.01,
        'total_tokens_in' => 100,
        'total_tokens_out' => 50,
    ], $attrs));

    return $run;
}

it('renders the datasets list view', function (): void {
    Livewire::test(DatasetsList::class)
        ->assertOk()
        ->assertSee('Datasets');
});

it('shows all datasets ordered by name', function (): void {
    explorerDataset('zeta-ds');
    explorerDataset('alpha-ds');
    explorerDataset('mu-ds');

    Livewire::test(DatasetsList::class)
        ->assertSeeInOrder(['alpha-ds', 'mu-ds', 'zeta-ds']);
});

it('displays case count per dataset', function (): void {
    explorerDataset('cases-ds', caseCount: 17);

    Livewire::test(DatasetsList::class)
        ->assertSee('cases-ds')
        ->assertSee('17');
});

it('displays runs count per dataset', function (): void {
    $dataset = explorerDataset('runs-ds');
    explorerRun($dataset);
    explorerRun($dataset);
    explorerRun($dataset);

    Livewire::test(DatasetsList::class)
        ->assertSee('runs-ds')
        ->assertSee('3');
});

it('displays the last run timestamp', function (): void {
    $dataset = explorerDataset('last-run-ds');
    $run = explorerRun($dataset);
    $run->created_at = now()->subHours(2);
    $run->save();

    Livewire::test(DatasetsList::class)
        ->assertSee('last-run-ds')
        ->assertSee('ago');
});

it('displays avg cost', function (): void {
    $dataset = explorerDataset('avg-cost-ds');
    explorerRun($dataset, ['total_cost_usd' => 0.1]);
    explorerRun($dataset, ['total_cost_usd' => 0.3]);

    Livewire::test(DatasetsList::class)
        ->assertSee('avg-cost-ds')
        ->assertSee('0.2');
});

it('displays avg duration', function (): void {
    $dataset = explorerDataset('avg-dur-ds');
    explorerRun($dataset, ['duration_ms' => 100.0]);
    explorerRun($dataset, ['duration_ms' => 300.0]);

    Livewire::test(DatasetsList::class)
        ->assertSee('avg-dur-ds')
        ->assertSee('200');
});

it('shows a sparkline for the last 30 days', function (): void {
    $dataset = explorerDataset('spark-ds');
    $run = explorerRun($dataset, ['passed' => true]);
    $run->created_at = now()->subDays(2);
    $run->save();

    $component = Livewire::test(DatasetsList::class);

    $component->assertSee('<svg', false);
    $component->assertSee('sparkline', false);
    $component->assertSee('<path', false);
});

it('handles datasets with no runs gracefully', function (): void {
    explorerDataset('empty-ds');

    $component = Livewire::test(DatasetsList::class);

    $component->assertSee('empty-ds');
    $component->assertSee('&mdash;', false);
});

it('respects the viewEvals gate', function (): void {
    Gate::define('viewEvals', fn ($user = null) => false);

    $response = $this->get('/evals/datasets');

    $response->assertForbidden();
});

it('links to the runs list filtered by dataset', function (): void {
    $dataset = explorerDataset('link-ds');
    explorerRun($dataset);

    Livewire::test(DatasetsList::class)
        ->assertSee('runs?dataset=link-ds', false);
});
