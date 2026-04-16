<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Models\ShadowEval;

/**
 * @param  array<string, mixed>  $overrides
 */
function makeCaptureForEval(array $overrides = []): ShadowCapture
{
    $attributes = array_merge([
        'agent_class' => 'App\\Agents\\Alpha',
        'prompt_hash' => str_repeat('c', 64),
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

/**
 * @param  array<string, mixed>  $overrides
 */
function makeEval(array $overrides = []): ShadowEval
{
    $capture = $overrides['capture'] ?? makeCaptureForEval();
    unset($overrides['capture']);

    $attributes = array_merge([
        'capture_id' => $capture->id,
        'agent_class' => $capture->agent_class,
        'passed' => true,
        'total_assertions' => 2,
        'passed_assertions' => 2,
        'failed_assertions' => 0,
        'assertion_results' => [
            ['name' => 'contains', 'passed' => true, 'reason' => 'ok'],
            ['name' => 'cost_limit', 'passed' => true, 'reason' => 'within budget'],
        ],
        'judge_cost_usd' => null,
        'judge_tokens_in' => null,
        'judge_tokens_out' => null,
        'evaluation_duration_ms' => 12.345,
        'evaluated_at' => Carbon::parse('2026-04-17 12:05:00'),
    ], $overrides);

    $eval = new ShadowEval;
    $eval->fill($attributes);
    $eval->save();

    return $eval;
}

it('creates a shadow eval with ULID primary key', function (): void {
    $eval = makeEval();

    expect($eval->id)->toBeString()
        ->and(strlen($eval->id))->toBe(26);
});

it('persists and retrieves all expected fields', function (): void {
    $capture = makeCaptureForEval(['agent_class' => 'App\\Agents\\Beta']);

    $eval = makeEval([
        'capture' => $capture,
        'agent_class' => 'App\\Agents\\Beta',
        'passed' => false,
        'total_assertions' => 3,
        'passed_assertions' => 1,
        'failed_assertions' => 2,
        'assertion_results' => [
            ['name' => 'contains', 'passed' => true, 'reason' => 'ok'],
            ['name' => 'rubric', 'passed' => false, 'reason' => 'tone wrong', 'score' => 0.3],
        ],
        'judge_cost_usd' => 0.002345,
        'judge_tokens_in' => 300,
        'judge_tokens_out' => 50,
        'evaluation_duration_ms' => 987.654,
    ]);

    $eval->refresh();

    expect($eval->capture_id)->toBe($capture->id)
        ->and($eval->agent_class)->toBe('App\\Agents\\Beta')
        ->and($eval->passed)->toBeFalse()
        ->and($eval->total_assertions)->toBe(3)
        ->and($eval->passed_assertions)->toBe(1)
        ->and($eval->failed_assertions)->toBe(2)
        ->and($eval->judge_cost_usd)->toBe(0.002345)
        ->and($eval->judge_tokens_in)->toBe(300)
        ->and($eval->judge_tokens_out)->toBe(50)
        ->and($eval->evaluation_duration_ms)->toBe(987.654);
});

it('casts assertion_results to an array', function (): void {
    $eval = makeEval([
        'assertion_results' => [
            ['name' => 'contains', 'passed' => true, 'reason' => 'ok', 'score' => null, 'metadata' => []],
            ['name' => 'rubric', 'passed' => false, 'reason' => 'bad', 'score' => 0.2, 'metadata' => ['judge_model' => 'claude-haiku-4-5']],
        ],
    ]);

    $eval->refresh();

    expect($eval->assertion_results)->toBeArray()
        ->and($eval->assertion_results)->toHaveCount(2)
        ->and($eval->assertion_results[0]['name'])->toBe('contains')
        ->and($eval->assertion_results[1]['metadata']['judge_model'])->toBe('claude-haiku-4-5');
});

it('casts numeric and boolean fields correctly', function (): void {
    $eval = makeEval([
        'passed' => true,
        'total_assertions' => 5,
        'passed_assertions' => 4,
        'failed_assertions' => 1,
        'judge_cost_usd' => 0.000987,
        'judge_tokens_in' => 42,
        'judge_tokens_out' => 7,
        'evaluation_duration_ms' => 99.123,
    ]);

    $eval->refresh();

    expect($eval->passed)->toBeBool()->toBeTrue()
        ->and($eval->total_assertions)->toBeInt()->toBe(5)
        ->and($eval->passed_assertions)->toBeInt()->toBe(4)
        ->and($eval->failed_assertions)->toBeInt()->toBe(1)
        ->and($eval->judge_cost_usd)->toBeFloat()->toBe(0.000987)
        ->and($eval->judge_tokens_in)->toBeInt()->toBe(42)
        ->and($eval->judge_tokens_out)->toBeInt()->toBe(7)
        ->and($eval->evaluation_duration_ms)->toBeFloat()->toBe(99.123);
});

it('casts evaluated_at to Carbon', function (): void {
    $eval = makeEval([
        'evaluated_at' => Carbon::parse('2026-01-15 08:30:00'),
    ]);

    $eval->refresh();

    expect($eval->evaluated_at)->toBeInstanceOf(Carbon::class)
        ->and($eval->evaluated_at->toDateTimeString())->toBe('2026-01-15 08:30:00')
        ->and($eval->created_at)->toBeInstanceOf(Carbon::class)
        ->and($eval->updated_at)->toBeInstanceOf(Carbon::class);
});

it('belongs to a shadow capture', function (): void {
    $capture = makeCaptureForEval(['agent_class' => 'App\\Agents\\Gamma']);
    $eval = makeEval(['capture' => $capture, 'agent_class' => 'App\\Agents\\Gamma']);

    $eval->refresh();

    expect($eval->capture)->toBeInstanceOf(ShadowCapture::class)
        ->and($eval->capture?->id)->toBe($capture->id)
        ->and($eval->capture?->agent_class)->toBe('App\\Agents\\Gamma');
});

it('filters by agent class via scopeForAgent', function (): void {
    $alphaCapture = makeCaptureForEval(['agent_class' => 'App\\Agents\\Alpha']);
    $betaCapture = makeCaptureForEval(['agent_class' => 'App\\Agents\\Beta']);

    makeEval(['capture' => $alphaCapture, 'agent_class' => 'App\\Agents\\Alpha']);
    makeEval(['capture' => $alphaCapture, 'agent_class' => 'App\\Agents\\Alpha']);
    makeEval(['capture' => $betaCapture, 'agent_class' => 'App\\Agents\\Beta']);

    expect(ShadowEval::forAgent('App\\Agents\\Alpha')->count())->toBe(2)
        ->and(ShadowEval::forAgent('App\\Agents\\Beta')->count())->toBe(1)
        ->and(ShadowEval::forAgent('App\\Agents\\Unknown')->count())->toBe(0);
});

it('filters passed results via scopePassedOnly', function (): void {
    makeEval(['passed' => true]);
    makeEval(['passed' => true]);
    makeEval(['passed' => false]);

    expect(ShadowEval::passedOnly()->count())->toBe(2);
});

it('filters failed results via scopeFailedOnly', function (): void {
    makeEval(['passed' => true]);
    makeEval(['passed' => false]);
    makeEval(['passed' => false]);

    expect(ShadowEval::failedOnly()->count())->toBe(2);
});

it('filters by evaluation date range via scopeEvaluatedBetween', function (): void {
    makeEval(['evaluated_at' => Carbon::parse('2026-04-10 10:00:00')]);
    makeEval(['evaluated_at' => Carbon::parse('2026-04-15 10:00:00')]);
    makeEval(['evaluated_at' => Carbon::parse('2026-04-20 10:00:00')]);

    $from = Carbon::parse('2026-04-12 00:00:00');
    $to = Carbon::parse('2026-04-18 00:00:00');

    expect(ShadowEval::evaluatedBetween($from, $to)->count())->toBe(1);
});
