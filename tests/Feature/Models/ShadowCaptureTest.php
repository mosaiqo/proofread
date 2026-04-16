<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Mosaiqo\Proofread\Models\ShadowCapture;

/**
 * @param  array<string, mixed>  $overrides
 */
function makeCapture(array $overrides = []): ShadowCapture
{
    $attributes = array_merge([
        'agent_class' => 'App\\Agents\\Backend',
        'prompt_hash' => str_repeat('a', 64),
        'input_payload' => ['prompt' => 'hello'],
        'output' => 'hi there',
        'tokens_in' => 100,
        'tokens_out' => 20,
        'cost_usd' => 0.001234,
        'latency_ms' => 250.5,
        'model_used' => 'claude-haiku-4-5',
        'captured_at' => Carbon::parse('2026-04-17 12:00:00'),
        'sample_rate' => 0.1,
        'is_anonymized' => true,
    ], $overrides);

    $capture = new ShadowCapture;
    $capture->fill($attributes);
    $capture->save();

    return $capture;
}

it('creates a capture with a ULID primary key', function (): void {
    $capture = makeCapture();

    expect($capture->id)->toBeString()
        ->and(strlen($capture->id))->toBe(26);
});

it('persists and retrieves all expected fields', function (): void {
    $capture = makeCapture([
        'agent_class' => 'App\\Agents\\Frontend',
        'prompt_hash' => str_repeat('b', 64),
        'input_payload' => ['prompt' => 'write a button'],
        'output' => '<button>Go</button>',
        'tokens_in' => 500,
        'tokens_out' => 200,
        'cost_usd' => 0.005678,
        'latency_ms' => 1234.567,
        'model_used' => 'claude-sonnet-4-5',
        'sample_rate' => 0.25,
        'is_anonymized' => false,
    ]);

    $capture->refresh();

    expect($capture->agent_class)->toBe('App\\Agents\\Frontend')
        ->and($capture->prompt_hash)->toBe(str_repeat('b', 64))
        ->and($capture->output)->toBe('<button>Go</button>')
        ->and($capture->tokens_in)->toBe(500)
        ->and($capture->tokens_out)->toBe(200)
        ->and($capture->cost_usd)->toBe(0.005678)
        ->and($capture->latency_ms)->toBe(1234.567)
        ->and($capture->model_used)->toBe('claude-sonnet-4-5')
        ->and($capture->sample_rate)->toBe(0.25)
        ->and($capture->is_anonymized)->toBeFalse();
});

it('casts input_payload to an array', function (): void {
    $capture = makeCapture([
        'input_payload' => ['nested' => ['key' => 'value'], 'list' => [1, 2, 3]],
    ]);

    $capture->refresh();

    expect($capture->input_payload)->toBeArray()
        ->and($capture->input_payload['nested']['key'])->toBe('value')
        ->and($capture->input_payload['list'])->toBe([1, 2, 3]);
});

it('casts timestamps correctly', function (): void {
    $capture = makeCapture([
        'captured_at' => Carbon::parse('2026-01-15 08:30:00'),
    ]);

    $capture->refresh();

    expect($capture->captured_at)->toBeInstanceOf(Carbon::class)
        ->and($capture->captured_at->toDateTimeString())->toBe('2026-01-15 08:30:00')
        ->and($capture->created_at)->toBeInstanceOf(Carbon::class)
        ->and($capture->updated_at)->toBeInstanceOf(Carbon::class);
});

it('casts numeric fields correctly', function (): void {
    $capture = makeCapture([
        'tokens_in' => 42,
        'tokens_out' => 7,
        'cost_usd' => 0.000987,
        'latency_ms' => 99.123,
        'sample_rate' => 0.05,
    ]);

    $capture->refresh();

    expect($capture->tokens_in)->toBeInt()->toBe(42)
        ->and($capture->tokens_out)->toBeInt()->toBe(7)
        ->and($capture->cost_usd)->toBeFloat()->toBe(0.000987)
        ->and($capture->latency_ms)->toBeFloat()->toBe(99.123)
        ->and($capture->sample_rate)->toBeFloat()->toBe(0.05);
});

it('casts is_anonymized as boolean', function (): void {
    $capture = makeCapture(['is_anonymized' => true]);
    $capture->refresh();
    expect($capture->is_anonymized)->toBeTrue();

    $capture2 = makeCapture(['is_anonymized' => false]);
    $capture2->refresh();
    expect($capture2->is_anonymized)->toBeFalse();
});

it('filters by agent class via scopeForAgent', function (): void {
    makeCapture(['agent_class' => 'App\\Agents\\Alpha']);
    makeCapture(['agent_class' => 'App\\Agents\\Alpha']);
    makeCapture(['agent_class' => 'App\\Agents\\Beta']);

    expect(ShadowCapture::forAgent('App\\Agents\\Alpha')->count())->toBe(2)
        ->and(ShadowCapture::forAgent('App\\Agents\\Beta')->count())->toBe(1)
        ->and(ShadowCapture::forAgent('App\\Agents\\Unknown')->count())->toBe(0);
});

it('filters by captured_at range via scopeCapturedBetween', function (): void {
    makeCapture(['captured_at' => Carbon::parse('2026-04-10 10:00:00')]);
    makeCapture(['captured_at' => Carbon::parse('2026-04-15 10:00:00')]);
    makeCapture(['captured_at' => Carbon::parse('2026-04-20 10:00:00')]);

    $from = Carbon::parse('2026-04-12 00:00:00');
    $to = Carbon::parse('2026-04-18 00:00:00');

    expect(ShadowCapture::capturedBetween($from, $to)->count())->toBe(1);
});
