<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Pricing\PricingTable;
use Mosaiqo\Proofread\Simulation\CostSimulationReport;
use Mosaiqo\Proofread\Simulation\CostSimulator;

/**
 * @param  array<string, mixed>  $overrides
 */
function seedCostCapture(array $overrides = []): ShadowCapture
{
    $defaults = [
        'agent_class' => 'App\\Agents\\SupportAgent',
        'prompt_hash' => bin2hex(random_bytes(32)),
        'input_payload' => ['prompt' => 'hi'],
        'output' => 'ok',
        'tokens_in' => 1_000,
        'tokens_out' => 500,
        'cost_usd' => null,
        'latency_ms' => 10.0,
        'model_used' => 'claude-sonnet-4-6',
        'captured_at' => Carbon::now(),
        'sample_rate' => 1.0,
        'is_anonymized' => true,
    ];

    $attributes = array_merge($defaults, $overrides);

    $capture = new ShadowCapture;
    $capture->fill($attributes);
    $capture->save();

    return $capture;
}

function makeSimulator(): CostSimulator
{
    /** @var PricingTable $pricing */
    $pricing = app(PricingTable::class);

    return new CostSimulator($pricing);
}

it('projects cost for the current model based on stored captures', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    for ($i = 0; $i < 3; $i++) {
        seedCostCapture([
            'agent_class' => $agent,
            'tokens_in' => 1_000_000,
            'tokens_out' => 1_000_000,
            'model_used' => 'claude-sonnet-4-6',
        ]);
    }

    $report = makeSimulator()->simulate(
        $agent,
        Carbon::now()->subDay(),
        Carbon::now()->addMinute(),
    );

    expect($report)->toBeInstanceOf(CostSimulationReport::class)
        ->and($report->agentClass)->toBe($agent)
        ->and($report->totalCaptures)->toBe(3)
        ->and($report->current->model)->toBe('claude-sonnet-4-6')
        ->and($report->current->coveredCaptures)->toBe(3)
        ->and($report->current->skippedCaptures)->toBe(0)
        // Each capture: 1M input * $3 + 1M output * $15 = $18 per capture * 3 = $54
        ->and($report->current->totalCost)->toBe(54.0)
        ->and($report->current->perCaptureCost)->toBe(18.0);
});

it('projects cost for alternative models', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCostCapture([
        'agent_class' => $agent,
        'tokens_in' => 1_000_000,
        'tokens_out' => 1_000_000,
        'model_used' => 'claude-sonnet-4-6',
    ]);

    $report = makeSimulator()->simulate(
        $agent,
        Carbon::now()->subDay(),
        Carbon::now()->addMinute(),
        ['claude-haiku-4-5'],
    );

    expect($report->projections)->toHaveKey('claude-haiku-4-5')
        // 1M * $1 + 1M * $5 = $6
        ->and($report->projections['claude-haiku-4-5']->totalCost)->toBe(6.0)
        ->and($report->projections['claude-haiku-4-5']->perCaptureCost)->toBe(6.0)
        ->and($report->projections['claude-haiku-4-5']->coveredCaptures)->toBe(1);
});

it('respects the date range', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCostCapture([
        'agent_class' => $agent,
        'captured_at' => Carbon::now()->subDays(10),
    ]);
    seedCostCapture([
        'agent_class' => $agent,
        'captured_at' => Carbon::now()->subHours(2),
    ]);

    $report = makeSimulator()->simulate(
        $agent,
        Carbon::now()->subDay(),
        Carbon::now(),
    );

    expect($report->totalCaptures)->toBe(1);
});

it('uses all pricing models when alternatives list is empty', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCostCapture([
        'agent_class' => $agent,
        'model_used' => 'claude-sonnet-4-6',
    ]);

    $report = makeSimulator()->simulate(
        $agent,
        Carbon::now()->subDay(),
        Carbon::now()->addMinute(),
    );

    /** @var PricingTable $pricing */
    $pricing = app(PricingTable::class);
    $expectedCount = count($pricing->all()) - 1;

    expect($report->projections)->toHaveCount($expectedCount);
});

it('excludes the current model from alternatives', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCostCapture([
        'agent_class' => $agent,
        'model_used' => 'claude-sonnet-4-6',
    ]);

    $report = makeSimulator()->simulate(
        $agent,
        Carbon::now()->subDay(),
        Carbon::now()->addMinute(),
    );

    expect($report->projections)->not->toHaveKey('claude-sonnet-4-6');
});

it('reports skipped captures when tokens are missing', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCostCapture([
        'agent_class' => $agent,
        'tokens_in' => 1_000,
        'tokens_out' => 1_000,
        'model_used' => 'claude-sonnet-4-6',
    ]);
    seedCostCapture([
        'agent_class' => $agent,
        'tokens_in' => null,
        'tokens_out' => null,
        'model_used' => 'claude-sonnet-4-6',
    ]);

    $report = makeSimulator()->simulate(
        $agent,
        Carbon::now()->subDay(),
        Carbon::now()->addMinute(),
        ['claude-haiku-4-5'],
    );

    expect($report->totalCaptures)->toBe(2)
        ->and($report->current->coveredCaptures)->toBe(1)
        ->and($report->current->skippedCaptures)->toBe(1)
        ->and($report->projections['claude-haiku-4-5']->coveredCaptures)->toBe(1)
        ->and($report->projections['claude-haiku-4-5']->skippedCaptures)->toBe(1);
});

it('reports zero when no captures match', function (): void {
    $report = makeSimulator()->simulate(
        'App\\Agents\\NoCaptures',
        Carbon::now()->subDay(),
        Carbon::now(),
    );

    expect($report->totalCaptures)->toBe(0)
        ->and($report->current->totalCost)->toBe(0.0)
        ->and($report->current->coveredCaptures)->toBe(0)
        ->and($report->projections)->toBe([]);
});

it('returns a cheapest alternative', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCostCapture([
        'agent_class' => $agent,
        'tokens_in' => 1_000_000,
        'tokens_out' => 1_000_000,
        'model_used' => 'claude-sonnet-4-6',
    ]);

    $report = makeSimulator()->simulate(
        $agent,
        Carbon::now()->subDay(),
        Carbon::now()->addMinute(),
        ['claude-haiku-4-5', 'claude-opus-4-6'],
    );

    $cheapest = $report->cheapestAlternative();

    expect($cheapest)->not->toBeNull();
    expect($cheapest?->model)->toBe('claude-haiku-4-5');
});

it('computes savings vs current correctly', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCostCapture([
        'agent_class' => $agent,
        'tokens_in' => 1_000_000,
        'tokens_out' => 1_000_000,
        'model_used' => 'claude-sonnet-4-6',
    ]);

    $report = makeSimulator()->simulate(
        $agent,
        Carbon::now()->subDay(),
        Carbon::now()->addMinute(),
        ['claude-haiku-4-5'],
    );

    // Sonnet: $18, Haiku: $6 => savings = $12
    expect($report->savingsVs('claude-haiku-4-5'))->toBe(12.0);
});

it('filters cheaper alternatives', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    seedCostCapture([
        'agent_class' => $agent,
        'tokens_in' => 1_000_000,
        'tokens_out' => 1_000_000,
        'model_used' => 'claude-sonnet-4-6',
    ]);

    $report = makeSimulator()->simulate(
        $agent,
        Carbon::now()->subDay(),
        Carbon::now()->addMinute(),
        ['claude-haiku-4-5', 'claude-opus-4-6'],
    );

    $cheaper = $report->cheaperThanCurrent();

    expect($cheaper)->toHaveCount(1)
        ->and($cheaper[0]->model)->toBe('claude-haiku-4-5');
});

it('identifies the most common model as current', function (): void {
    $agent = 'App\\Agents\\SupportAgent';
    for ($i = 0; $i < 3; $i++) {
        seedCostCapture([
            'agent_class' => $agent,
            'model_used' => 'claude-sonnet-4-6',
        ]);
    }
    seedCostCapture([
        'agent_class' => $agent,
        'model_used' => 'claude-haiku-4-5',
    ]);

    $report = makeSimulator()->simulate(
        $agent,
        Carbon::now()->subDay(),
        Carbon::now()->addMinute(),
    );

    expect($report->current->model)->toBe('claude-sonnet-4-6');
});

it('handles cache and reasoning tokens correctly', function (): void {
    $agent = 'App\\Agents\\SupportAgent';

    config()->set('proofread.pricing.models.test-cache-model', [
        'input_per_1m' => 1.0,
        'output_per_1m' => 2.0,
        'cache_read_per_1m' => 0.5,
        'cache_write_per_1m' => 1.5,
    ]);
    /** @var array<string, array<string, mixed>> $models */
    $models = config()->get('proofread.pricing.models');
    app()->instance(PricingTable::class, PricingTable::fromArray($models));

    seedCostCapture([
        'agent_class' => $agent,
        'tokens_in' => 1_000_000,
        'tokens_out' => 1_000_000,
        'model_used' => 'test-cache-model',
    ]);

    $report = makeSimulator()->simulate(
        $agent,
        Carbon::now()->subDay(),
        Carbon::now()->addMinute(),
        ['claude-haiku-4-5'],
    );

    expect($report->current->model)->toBe('test-cache-model')
        // 1M * $1 + 1M * $2 = $3
        ->and($report->current->totalCost)->toBe(3.0);
});
