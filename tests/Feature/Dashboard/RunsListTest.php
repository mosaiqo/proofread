<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Livewire;
use Mosaiqo\Proofread\Http\Livewire\RunsList;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalRun;

uses(RefreshDatabase::class);

function listTestDataset(string $name): EvalDataset
{
    /** @var EvalDataset $dataset */
    $dataset = EvalDataset::query()->create([
        'name' => $name,
        'case_count' => 5,
        'checksum' => null,
    ]);

    return $dataset;
}

/**
 * @param  array<string, mixed>  $attrs
 */
function listTestRun(array $attrs = []): EvalRun
{
    $datasetName = isset($attrs['dataset_name']) && is_string($attrs['dataset_name'])
        ? $attrs['dataset_name']
        : 'd-'.uniqid();

    $dataset = listTestDataset($datasetName);

    /** @var EvalRun $run */
    $run = EvalRun::query()->create(array_merge([
        'dataset_id' => $dataset->id,
        'dataset_name' => $dataset->name,
        'subject_type' => 'agent',
        'subject_class' => null,
        'passed' => true,
        'pass_count' => 5,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 5,
        'duration_ms' => 120.5,
        'total_cost_usd' => 0.0125,
        'total_tokens_in' => 100,
        'total_tokens_out' => 50,
    ], $attrs));

    return $run;
}

it('renders the runs list view', function (): void {
    Livewire::test(RunsList::class)
        ->assertOk()
        ->assertSee('Runs');
});

it('displays recent runs in the table', function (): void {
    $older = listTestRun(['dataset_name' => 'alpha-eval']);
    $older->created_at = now()->subHours(2);
    $older->save();

    $newer = listTestRun(['dataset_name' => 'beta-eval']);
    $newer->created_at = now()->subMinutes(5);
    $newer->save();

    Livewire::test(RunsList::class)
        ->assertSee('alpha-eval')
        ->assertSee('beta-eval')
        ->assertSeeInOrder(['beta-eval', 'alpha-eval']);
});

it('shows a pass stat card for the last 24 hours', function (): void {
    $run = listTestRun(['passed' => true]);
    $run->created_at = now()->subHour();
    $run->save();

    $failed = listTestRun(['passed' => false, 'pass_count' => 2, 'fail_count' => 3]);
    $failed->created_at = now()->subHours(3);
    $failed->save();

    Livewire::test(RunsList::class)
        ->assertSee('Last 24h', false)
        ->assertSet('stats.last_24h_pass_rate', 0.5);
});

it('shows a pass stat card for the current week', function (): void {
    listTestRun(['passed' => true])->update(['created_at' => now()->subDays(2)]);
    listTestRun(['passed' => true])->update(['created_at' => now()->subDays(3)]);
    listTestRun(['passed' => false])->update(['created_at' => now()->subDays(1)]);

    Livewire::test(RunsList::class)
        ->assertSee('This week', false)
        ->assertSet('stats.this_week_pass_rate', 2 / 3);
});

it('shows an all-time stat card with run count and total cost', function (): void {
    listTestRun(['total_cost_usd' => 0.10]);
    listTestRun(['total_cost_usd' => 0.25]);

    Livewire::test(RunsList::class)
        ->assertSee('All-time', false)
        ->assertSet('stats.total_runs', 2)
        ->assertSet('stats.total_cost_usd', 0.35);
});

it('filters by dataset name', function (): void {
    $keep = listTestRun(['dataset_name' => 'foo-dataset']);
    $drop = listTestRun(['dataset_name' => 'bar-dataset']);

    Livewire::test(RunsList::class)
        ->set('datasetFilter', 'foo-dataset')
        ->assertSee('run-'.$keep->id, false)
        ->assertDontSee('run-'.$drop->id, false);
});

it('filters by status passed', function (): void {
    $pass = listTestRun(['dataset_name' => 'pass-run', 'passed' => true]);
    $fail = listTestRun(['dataset_name' => 'fail-run', 'passed' => false]);

    Livewire::test(RunsList::class)
        ->set('statusFilter', 'passed')
        ->assertSee('run-'.$pass->id, false)
        ->assertDontSee('run-'.$fail->id, false);
});

it('filters by status failed', function (): void {
    $pass = listTestRun(['dataset_name' => 'pass-run', 'passed' => true]);
    $fail = listTestRun(['dataset_name' => 'fail-run', 'passed' => false]);

    Livewire::test(RunsList::class)
        ->set('statusFilter', 'failed')
        ->assertSee('run-'.$fail->id, false)
        ->assertDontSee('run-'.$pass->id, false);
});

it('searches by dataset name substring', function (): void {
    $match = listTestRun(['dataset_name' => 'sentiment-classification']);
    $nonMatch = listTestRun(['dataset_name' => 'code-review']);

    Livewire::test(RunsList::class)
        ->set('search', 'sent')
        ->assertSee('run-'.$match->id, false)
        ->assertDontSee('run-'.$nonMatch->id, false);
});

it('populates dataset options from the database', function (): void {
    listTestDataset('alpha');
    listTestDataset('beta');

    Livewire::test(RunsList::class)
        ->assertSee('alpha')
        ->assertSee('beta');
});

it('resets pagination when filters change', function (): void {
    for ($i = 0; $i < 30; $i++) {
        listTestRun(['dataset_name' => "run-$i"]);
    }

    $component = Livewire::test(RunsList::class)
        ->call('setPage', 2);

    expect($component->get('paginators')['page'] ?? null)->toBe(2);

    $component->set('search', 'run');

    expect($component->get('paginators')['page'] ?? null)->toBe(1);
});

it('respects the viewEvals gate', function (): void {
    Gate::define('viewEvals', fn ($user = null) => false);

    $response = $this->get('/evals/runs');

    $response->assertForbidden();
});

it('exposes filter state in URL query string', function (): void {
    $runsList = new RunsList;
    $reflection = new ReflectionClass($runsList);

    foreach (['datasetFilter', 'statusFilter', 'search'] as $property) {
        $attributes = $reflection->getProperty($property)->getAttributes(Url::class);
        expect($attributes)->not->toBeEmpty();
    }
});

it('paginates when runs exceed page size', function (): void {
    $runs = [];
    for ($i = 0; $i < 25; $i++) {
        $runs[$i] = listTestRun(['dataset_name' => "run-page-$i"]);
        // Stagger created_at so ordering is deterministic (newest last).
        $runs[$i]->created_at = now()->subSeconds(25 - $i);
        $runs[$i]->save();
    }

    // Page 1: 20 newest (indexes 24..5). The 5 oldest (0..4) are on page 2.
    Livewire::test(RunsList::class)
        ->assertSee('run-'.$runs[24]->id, false)
        ->assertDontSee('run-'.$runs[0]->id, false);
});

it('shows an empty state when there are no runs', function (): void {
    Livewire::test(RunsList::class)
        ->assertSee('No runs yet');
});
