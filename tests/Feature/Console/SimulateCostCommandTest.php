<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\EchoAgent;

/**
 * @param  array<string, mixed>  $overrides
 */
function seedCostSimCapture(array $overrides = []): ShadowCapture
{
    $defaults = [
        'agent_class' => EchoAgent::class,
        'prompt_hash' => bin2hex(random_bytes(32)),
        'input_payload' => ['prompt' => 'hi'],
        'output' => 'ok',
        'tokens_in' => 1_000_000,
        'tokens_out' => 1_000_000,
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

it('simulates cost for an agent with shadow captures', function (): void {
    seedCostSimCapture();
    seedCostSimCapture();

    $exit = Artisan::call('evals:cost-simulate', [
        'agent' => EchoAgent::class,
        '--model' => 'claude-haiku-4-5',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Cost simulation for '.EchoAgent::class)
        ->and($output)->toContain('Current: claude-sonnet-4-6')
        ->and($output)->toContain('claude-haiku-4-5')
        ->and($output)->toContain('Captures: 2');
});

it('outputs JSON with --format=json', function (): void {
    seedCostSimCapture();

    $exit = Artisan::call('evals:cost-simulate', [
        'agent' => EchoAgent::class,
        '--format' => 'json',
        '--model' => 'claude-haiku-4-5',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0);

    $decoded = json_decode($output, true);

    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveKeys([
            'agent_class',
            'window',
            'total_captures',
            'current',
            'projections',
            'cheapest_alternative',
        ]);

    /** @var array<string, mixed> $decoded */
    expect($decoded['agent_class'])->toBe(EchoAgent::class)
        ->and($decoded['total_captures'])->toBe(1);

    /** @var array<string, mixed> $current */
    $current = $decoded['current'];
    expect($current)->toHaveKeys([
        'model',
        'total_cost',
        'per_capture_cost',
        'covered_captures',
        'skipped_captures',
    ])->and($current['model'])->toBe('claude-sonnet-4-6');
});

it('respects --days', function (): void {
    seedCostSimCapture(['captured_at' => Carbon::now()->subDays(30)]);
    seedCostSimCapture(['captured_at' => Carbon::now()->subDays(2)]);

    $exit = Artisan::call('evals:cost-simulate', [
        'agent' => EchoAgent::class,
        '--days' => 7,
        '--model' => 'claude-haiku-4-5',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Captures: 1');
});

it('respects --model repeatable', function (): void {
    seedCostSimCapture();

    $exit = Artisan::call('evals:cost-simulate', [
        'agent' => EchoAgent::class,
        '--model' => ['claude-haiku-4-5', 'gpt-4o'],
        '--format' => 'json',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0);

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($output, true);
    /** @var list<array<string, mixed>> $projections */
    $projections = $decoded['projections'];

    expect($projections)->toHaveCount(2);

    $models = array_map(static fn (array $p): string => (string) $p['model'], $projections);
    sort($models);
    expect($models)->toBe(['claude-haiku-4-5', 'gpt-4o']);
});

it('warns when there are no captures in the window', function (): void {
    $exit = Artisan::call('evals:cost-simulate', [
        'agent' => EchoAgent::class,
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('No shadow captures found');
});

it('exits 2 when agent does not exist', function (): void {
    $exit = Artisan::call('evals:cost-simulate', [
        'agent' => 'App\\NotARealAgent',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('not found');
});

it('exits 2 when agent does not implement Agent contract', function (): void {
    $exit = Artisan::call('evals:cost-simulate', [
        'agent' => stdClass::class,
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('must implement');
});

it('shows savings in the cheapest alternative summary', function (): void {
    seedCostSimCapture();

    $exit = Artisan::call('evals:cost-simulate', [
        'agent' => EchoAgent::class,
        '--model' => 'claude-haiku-4-5',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Cheapest: claude-haiku-4-5')
        ->and($output)->toContain('save');
});

it('shows + sign for more expensive alternatives', function (): void {
    seedCostSimCapture();

    $exit = Artisan::call('evals:cost-simulate', [
        'agent' => EchoAgent::class,
        '--model' => 'claude-opus-4-6',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('claude-opus-4-6')
        ->and($output)->toMatch('/\+\$\d/');
});

it('handles captures with missing tokens gracefully', function (): void {
    seedCostSimCapture();
    seedCostSimCapture([
        'tokens_in' => null,
        'tokens_out' => null,
    ]);

    $exit = Artisan::call('evals:cost-simulate', [
        'agent' => EchoAgent::class,
        '--model' => 'claude-haiku-4-5',
        '--format' => 'json',
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(0);

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($output, true);
    /** @var array<string, mixed> $current */
    $current = $decoded['current'];

    expect($current['covered_captures'])->toBe(1)
        ->and($current['skipped_captures'])->toBe(1);
});

it('exits 2 when --days is not a positive integer', function (): void {
    $exit = Artisan::call('evals:cost-simulate', [
        'agent' => EchoAgent::class,
        '--days' => 0,
    ]);
    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('--days');
});
