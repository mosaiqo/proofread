<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Livewire;
use Mosaiqo\Proofread\Http\Livewire\CostsBreakdown;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalRun;

uses(RefreshDatabase::class);

function costsDataset(string $name, int $caseCount = 3): EvalDataset
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
function costsRun(EvalDataset $dataset, array $attrs = []): EvalRun
{
    /** @var EvalRun $run */
    $run = EvalRun::query()->create(array_merge([
        'dataset_id' => $dataset->id,
        'dataset_name' => $dataset->name,
        'subject_type' => 'agent',
        'model' => 'claude-haiku-4-5',
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

it('renders the costs view', function (): void {
    Livewire::test(CostsBreakdown::class)
        ->assertOk()
        ->assertSee('Costs');
});

it('displays total cost across all runs', function (): void {
    $dataset = costsDataset('total-cost-ds');
    costsRun($dataset, ['total_cost_usd' => 0.1234]);
    costsRun($dataset, ['total_cost_usd' => 0.2000]);

    Livewire::test(CostsBreakdown::class)
        ->set('windowFilter', 'all')
        ->assertSee('Total cost', false)
        ->assertSee('0.3234');
});

it('displays total runs count', function (): void {
    $dataset = costsDataset('total-runs-ds');
    costsRun($dataset);
    costsRun($dataset);
    costsRun($dataset);

    Livewire::test(CostsBreakdown::class)
        ->set('windowFilter', 'all')
        ->assertSee('Total runs', false)
        ->assertSee('3');
});

it('computes avg cost per run correctly', function (): void {
    $dataset = costsDataset('avg-ds');
    costsRun($dataset, ['total_cost_usd' => 0.10]);
    costsRun($dataset, ['total_cost_usd' => 0.30]);

    Livewire::test(CostsBreakdown::class)
        ->set('windowFilter', 'all')
        ->assertSee('Avg cost per run', false)
        ->assertSee('0.2000');
});

it('identifies the most expensive model', function (): void {
    $dataset = costsDataset('model-ds');
    costsRun($dataset, ['model' => 'gpt-4o', 'total_cost_usd' => 0.50]);
    costsRun($dataset, ['model' => 'gpt-4o', 'total_cost_usd' => 0.30]);
    costsRun($dataset, ['model' => 'claude-haiku-4-5', 'total_cost_usd' => 0.05]);

    Livewire::test(CostsBreakdown::class)
        ->set('windowFilter', 'all')
        ->assertSee('Most expensive model', false)
        ->assertSee('gpt-4o');
});

it('breaks down cost by model', function (): void {
    $dataset = costsDataset('breakdown-model-ds');
    costsRun($dataset, ['model' => 'gpt-4o', 'total_cost_usd' => 0.40, 'total_tokens_in' => 1000, 'total_tokens_out' => 500]);
    costsRun($dataset, ['model' => 'claude-haiku-4-5', 'total_cost_usd' => 0.10, 'total_tokens_in' => 200, 'total_tokens_out' => 100]);

    Livewire::test(CostsBreakdown::class)
        ->set('windowFilter', 'all')
        ->assertSee('gpt-4o')
        ->assertSee('claude-haiku-4-5');
});

it('orders model breakdown by total cost desc', function (): void {
    $dataset = costsDataset('order-model-ds');
    costsRun($dataset, ['model' => 'cheap-model', 'total_cost_usd' => 0.01]);
    costsRun($dataset, ['model' => 'expensive-model', 'total_cost_usd' => 0.99]);
    costsRun($dataset, ['model' => 'mid-model', 'total_cost_usd' => 0.30]);

    Livewire::test(CostsBreakdown::class)
        ->set('windowFilter', 'all')
        ->assertSeeInOrder(['expensive-model', 'mid-model', 'cheap-model']);
});

it('computes model percentage correctly', function (): void {
    $dataset = costsDataset('percent-ds');
    costsRun($dataset, ['model' => 'alpha', 'total_cost_usd' => 0.75]);
    costsRun($dataset, ['model' => 'beta', 'total_cost_usd' => 0.25]);

    $component = Livewire::test(CostsBreakdown::class)
        ->set('windowFilter', 'all');

    /** @var list<array{model: string, percentage: float}> $byModel */
    $byModel = $component->viewData('byModel');

    $indexed = [];
    foreach ($byModel as $row) {
        $indexed[$row['model']] = $row['percentage'];
    }

    expect($indexed)->toHaveKey('alpha');
    expect($indexed)->toHaveKey('beta');
    expect($indexed['alpha'])->toBe(0.75);
    expect($indexed['beta'])->toBe(0.25);
});

it('breaks down cost by dataset', function (): void {
    $alpha = costsDataset('alpha-ds');
    $beta = costsDataset('beta-ds');

    costsRun($alpha, ['total_cost_usd' => 0.40]);
    costsRun($alpha, ['total_cost_usd' => 0.10]);
    costsRun($beta, ['total_cost_usd' => 0.20]);

    Livewire::test(CostsBreakdown::class)
        ->set('windowFilter', 'all')
        ->assertSee('alpha-ds')
        ->assertSee('beta-ds')
        ->assertSeeInOrder(['alpha-ds', 'beta-ds']);
});

it('links dataset rows to filtered runs list', function (): void {
    $dataset = costsDataset('linked-ds');
    costsRun($dataset, ['total_cost_usd' => 0.05]);

    Livewire::test(CostsBreakdown::class)
        ->set('windowFilter', 'all')
        ->assertSee('runs?dataset=linked-ds', false);
});

it('filters by 7d window', function (): void {
    $dataset = costsDataset('win7-ds');

    $recent = costsRun($dataset, ['total_cost_usd' => 0.10]);
    $recent->created_at = now()->subDays(2);
    $recent->save();

    $old = costsRun($dataset, ['total_cost_usd' => 0.90]);
    $old->created_at = now()->subDays(15);
    $old->save();

    Livewire::test(CostsBreakdown::class)
        ->set('windowFilter', '7d')
        ->assertSet('windowFilter', '7d')
        ->assertSee('0.1000');
});

it('filters by 30d window', function (): void {
    $dataset = costsDataset('win30-ds');

    $recent = costsRun($dataset, ['total_cost_usd' => 0.10]);
    $recent->created_at = now()->subDays(10);
    $recent->save();

    $old = costsRun($dataset, ['total_cost_usd' => 0.90]);
    $old->created_at = now()->subDays(60);
    $old->save();

    Livewire::test(CostsBreakdown::class)
        ->set('windowFilter', '30d')
        ->assertSee('0.1000');
});

it('filters by all-time', function (): void {
    $dataset = costsDataset('winall-ds');

    $recent = costsRun($dataset, ['total_cost_usd' => 0.10]);
    $recent->created_at = now()->subDays(2);
    $recent->save();

    $old = costsRun($dataset, ['total_cost_usd' => 0.90]);
    $old->created_at = now()->subDays(120);
    $old->save();

    Livewire::test(CostsBreakdown::class)
        ->set('windowFilter', 'all')
        ->assertSee('1.0000');
});

it('renders daily cost trend with data points', function (): void {
    $dataset = costsDataset('trend-ds');

    $today = costsRun($dataset, ['total_cost_usd' => 0.05]);
    $today->created_at = now();
    $today->save();

    $older = costsRun($dataset, ['total_cost_usd' => 0.10]);
    $older->created_at = now()->subDays(3);
    $older->save();

    Livewire::test(CostsBreakdown::class)
        ->set('windowFilter', '30d')
        ->assertSee('<svg', false)
        ->assertSee('trend-chart', false)
        ->assertSee('<circle', false);
});

it('renders gracefully when no runs have cost', function (): void {
    Livewire::test(CostsBreakdown::class)
        ->assertOk()
        ->assertSee('Costs');
});

it('ignores runs without cost from totals', function (): void {
    $dataset = costsDataset('null-cost-ds');

    costsRun($dataset, ['total_cost_usd' => 0.20]);
    costsRun($dataset, ['total_cost_usd' => null]);

    $component = Livewire::test(CostsBreakdown::class)
        ->set('windowFilter', 'all');

    /** @var float $avg */
    $avg = $component->viewData('totalCost');
    expect($avg)->toBe(0.20);
});

it('respects the viewEvals gate', function (): void {
    Gate::define('viewEvals', fn ($user = null) => false);

    $response = $this->get('/evals/costs');

    $response->assertForbidden();
});

it('exposes window filter in URL', function (): void {
    $component = new CostsBreakdown;
    $reflection = new ReflectionClass($component);

    $attributes = $reflection->getProperty('windowFilter')->getAttributes(Url::class);
    expect($attributes)->not->toBeEmpty();
});
