<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Carbon;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Shadow\Jobs\PersistShadowCaptureJob;

function configureShadowSanitizer(): void
{
    /** @var Repository $config */
    $config = app('config');
    $config->set('proofread.shadow.sanitize', [
        'pii_keys' => ['email', 'phone', 'password'],
        'redact_patterns' => [
            '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/' => '[EMAIL]',
        ],
        'max_input_length' => 2000,
        'max_output_length' => 5000,
        'redacted_placeholder' => '[REDACTED]',
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function buildPayload(array $overrides = []): array
{
    return array_merge([
        'raw_input' => ['prompt' => 'hello world', 'attachments' => []],
        'output' => 'the output text',
        'tokens_in' => 100,
        'tokens_out' => 50,
        'model' => 'claude-haiku-4-5',
        'latency_ms' => 123.456,
        'captured_at' => Carbon::parse('2026-04-15 12:00:00'),
    ], $overrides);
}

it('sanitizes input before persisting', function (): void {
    configureShadowSanitizer();

    $payload = buildPayload([
        'raw_input' => [
            'prompt' => 'Contact me at alice@example.com please.',
            'attachments' => [],
            'email' => 'alice@example.com',
        ],
    ]);

    $job = new PersistShadowCaptureJob($payload, 'App\\Agents\\Alpha', 1.0);
    app()->call([$job, 'handle']);

    $capture = ShadowCapture::query()->firstOrFail();
    expect($capture->input_payload['prompt'])->toContain('[EMAIL]')
        ->and($capture->input_payload['email'])->toBe('[REDACTED]');
});

it('sanitizes output before persisting', function (): void {
    configureShadowSanitizer();

    $payload = buildPayload([
        'output' => 'Reply to foo@bar.com for details.',
    ]);

    $job = new PersistShadowCaptureJob($payload, 'App\\Agents\\Alpha', 1.0);
    app()->call([$job, 'handle']);

    $capture = ShadowCapture::query()->firstOrFail();
    expect($capture->output)->toBe('Reply to [EMAIL] for details.');
});

it('computes prompt_hash from the raw input', function (): void {
    configureShadowSanitizer();

    $rawInput = ['prompt' => 'same prompt', 'attachments' => []];
    $expected = hash('sha256', (string) json_encode($rawInput));

    $job1 = new PersistShadowCaptureJob(buildPayload(['raw_input' => $rawInput]), 'App\\Agents\\Alpha', 1.0);
    app()->call([$job1, 'handle']);
    $job2 = new PersistShadowCaptureJob(buildPayload(['raw_input' => $rawInput]), 'App\\Agents\\Beta', 1.0);
    app()->call([$job2, 'handle']);

    $captures = ShadowCapture::query()->orderBy('created_at')->get();
    expect($captures)->toHaveCount(2)
        ->and($captures[0]->prompt_hash)->toBe($expected)
        ->and($captures[1]->prompt_hash)->toBe($expected);
});

it('computes cost_usd from the pricing table', function (): void {
    configureShadowSanitizer();

    $payload = buildPayload([
        'model' => 'claude-haiku-4-5',
        'tokens_in' => 1_000_000,
        'tokens_out' => 1_000_000,
    ]);

    $job = new PersistShadowCaptureJob($payload, 'App\\Agents\\Alpha', 1.0);
    app()->call([$job, 'handle']);

    $capture = ShadowCapture::query()->firstOrFail();
    expect($capture->cost_usd)->toBeFloat()
        ->and($capture->cost_usd)->toBeGreaterThan(0.0);
});

it('leaves cost_usd null when model is unknown', function (): void {
    configureShadowSanitizer();

    $payload = buildPayload([
        'model' => 'not-a-real-model',
    ]);

    $job = new PersistShadowCaptureJob($payload, 'App\\Agents\\Alpha', 1.0);
    app()->call([$job, 'handle']);

    $capture = ShadowCapture::query()->firstOrFail();
    expect($capture->cost_usd)->toBeNull();
});

it('persists the ShadowCapture with all fields', function (): void {
    configureShadowSanitizer();

    $payload = buildPayload([
        'model' => 'claude-haiku-4-5',
        'tokens_in' => 42,
        'tokens_out' => 7,
        'latency_ms' => 321.5,
        'captured_at' => Carbon::parse('2026-04-15 09:00:00'),
        'output' => 'plain output',
    ]);

    $job = new PersistShadowCaptureJob($payload, 'App\\Agents\\Alpha', 0.25);
    app()->call([$job, 'handle']);

    $capture = ShadowCapture::query()->firstOrFail();
    expect($capture->agent_class)->toBe('App\\Agents\\Alpha')
        ->and($capture->tokens_in)->toBe(42)
        ->and($capture->tokens_out)->toBe(7)
        ->and($capture->latency_ms)->toBe(321.5)
        ->and($capture->model_used)->toBe('claude-haiku-4-5')
        ->and($capture->sample_rate)->toBe(0.25)
        ->and($capture->output)->toBe('plain output')
        ->and($capture->prompt_hash)->toMatch('/^[0-9a-f]{64}$/');
});

it('sets is_anonymized to true by default', function (): void {
    configureShadowSanitizer();

    $job = new PersistShadowCaptureJob(buildPayload(), 'App\\Agents\\Alpha', 1.0);
    app()->call([$job, 'handle']);

    $capture = ShadowCapture::query()->firstOrFail();
    expect($capture->is_anonymized)->toBeTrue();
});

it('respects captured_at from the payload', function (): void {
    configureShadowSanitizer();

    $capturedAt = Carbon::parse('2026-01-01 00:00:00');
    $payload = buildPayload(['captured_at' => $capturedAt]);

    $job = new PersistShadowCaptureJob($payload, 'App\\Agents\\Alpha', 1.0);
    app()->call([$job, 'handle']);

    $capture = ShadowCapture::query()->firstOrFail();
    expect($capture->captured_at->toDateTimeString())->toBe('2026-01-01 00:00:00');
});

it('records the latency from the payload', function (): void {
    configureShadowSanitizer();

    $job = new PersistShadowCaptureJob(
        buildPayload(['latency_ms' => 987.654]),
        'App\\Agents\\Alpha',
        1.0,
    );
    app()->call([$job, 'handle']);

    $capture = ShadowCapture::query()->firstOrFail();
    expect($capture->latency_ms)->toBe(987.654);
});
