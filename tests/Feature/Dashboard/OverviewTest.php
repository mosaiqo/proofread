<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Mosaiqo\Proofread\Http\Livewire\Overview;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalRun;

uses(RefreshDatabase::class);

function overviewDataset(string $name, int $caseCount = 3): EvalDataset
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
function overviewRun(EvalDataset $dataset, array $attrs = []): EvalRun
{
    /** @var EvalRun $run */
    $run = EvalRun::query()->create(array_merge([
        'dataset_id' => $dataset->id,
        'dataset_name' => $dataset->name,
        'subject_type' => 'agent',
        'passed' => true,
        'pass_count' => 5,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 5,
        'duration_ms' => 120.0,
        'total_cost_usd' => 0.01,
        'total_tokens_in' => 100,
        'total_tokens_out' => 50,
    ], $attrs));

    return $run;
}

it('redirects root /evals to /evals/overview', function (): void {
    $response = $this->get('/evals');

    $response->assertRedirect('/evals/overview');
});

it('renders the overview view', function (): void {
    Livewire::test(Overview::class)
        ->assertOk()
        ->assertSee('Overview');
});

it('shows total runs count', function (): void {
    $dataset = overviewDataset('counting-ds');
    for ($i = 0; $i < 5; $i++) {
        overviewRun($dataset);
    }

    Livewire::test(Overview::class)
        ->assertSee('Total runs', false)
        ->assertSee('5');
});

it('shows total cost', function (): void {
    $dataset = overviewDataset('cost-ds');
    overviewRun($dataset, ['total_cost_usd' => 0.1234]);
    overviewRun($dataset, ['total_cost_usd' => 0.0001]);

    Livewire::test(Overview::class)
        ->assertSee('Total cost', false)
        ->assertSee('0.1235');
});

it('computes 7-day pass rate', function (): void {
    $dataset = overviewDataset('seven-ds');

    $passed = overviewRun($dataset, ['passed' => true]);
    $passed->created_at = now()->subDays(2);
    $passed->save();

    $failed = overviewRun($dataset, ['passed' => false]);
    $failed->created_at = now()->subDays(3);
    $failed->save();

    // Older than 7 days should not count.
    $old = overviewRun($dataset, ['passed' => false]);
    $old->created_at = now()->subDays(20);
    $old->save();

    Livewire::test(Overview::class)
        ->assertSee('Pass rate', false)
        ->assertSee('50.0%');
});

it('counts active datasets', function (): void {
    $active = overviewDataset('active-ds');
    $activeRun = overviewRun($active);
    $activeRun->created_at = now()->subDays(5);
    $activeRun->save();

    $stale = overviewDataset('stale-ds');
    $staleRun = overviewRun($stale);
    $staleRun->created_at = now()->subDays(45);
    $staleRun->save();

    // A dataset with no runs at all.
    overviewDataset('empty-ds');

    Livewire::test(Overview::class)
        ->assertSee('Active datasets', false)
        ->assertSet('globalStats.active_datasets', 1);
});

it('renders a 30-day trend chart with data points', function (): void {
    $dataset = overviewDataset('trend-ds');

    $today = overviewRun($dataset, ['passed' => true]);
    $today->created_at = now();
    $today->save();

    $old = overviewRun($dataset, ['passed' => false, 'pass_count' => 0, 'fail_count' => 5]);
    $old->created_at = now()->subDays(5);
    $old->save();

    Livewire::test(Overview::class)
        ->assertSee('<svg', false)
        ->assertSee('trend-chart', false)
        ->assertSee('<circle', false);
});

it('renders the trend chart gracefully with no runs', function (): void {
    Livewire::test(Overview::class)
        ->assertOk()
        ->assertSee('Overview');
});

it('lists top failing datasets', function (): void {
    $failing = overviewDataset('failing-ds');
    overviewRun($failing, ['passed' => false, 'pass_count' => 1, 'fail_count' => 4]);
    overviewRun($failing, ['passed' => false, 'pass_count' => 1, 'fail_count' => 4]);

    $passing = overviewDataset('passing-ds');
    overviewRun($passing, ['passed' => true]);

    Livewire::test(Overview::class)
        ->assertSee('Top failing datasets', false)
        ->assertSee('failing-ds');
});

it('lists recent regressions with compare links', function (): void {
    $dataset = overviewDataset('regression-ds');

    $base = overviewRun($dataset, [
        'passed' => true,
        'pass_count' => 5,
        'fail_count' => 0,
        'total_count' => 5,
    ]);
    $base->created_at = now()->subHours(3);
    $base->save();

    $head = overviewRun($dataset, [
        'passed' => false,
        'pass_count' => 2,
        'fail_count' => 3,
        'total_count' => 5,
    ]);
    $head->created_at = now()->subHour();
    $head->save();

    Livewire::test(Overview::class)
        ->assertSee('Recent regressions', false)
        ->assertSee('regression-ds')
        ->assertSee('base='.$base->id, false)
        ->assertSee('head='.$head->id, false);
});

it('lists recent runs up to 10', function (): void {
    $dataset = overviewDataset('recent-ds');

    for ($i = 0; $i < 12; $i++) {
        $run = overviewRun($dataset, ['dataset_name' => 'recent-ds']);
        $run->created_at = now()->subMinutes(12 - $i);
        $run->save();
    }

    $component = Livewire::test(Overview::class);

    /** @var array<int, EvalRun> $recentRuns */
    $recentRuns = $component->viewData('recentRuns');
    expect(count($recentRuns))->toBe(10);
});

it('respects the viewEvals gate', function (): void {
    Gate::define('viewEvals', fn ($user = null) => false);

    $response = $this->get('/evals/overview');

    $response->assertForbidden();
});

it('still allows direct access to /evals/runs', function (): void {
    $response = $this->get('/evals/runs');

    $response->assertOk();
});
