<?php

declare(strict_types=1);

use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use Mosaiqo\Proofread\Judge\Judge;
use Mosaiqo\Proofread\Judge\JudgeAgent;
use Mosaiqo\Proofread\Judge\JudgeException;
use Mosaiqo\Proofread\Judge\JudgeVerdict;

beforeEach(function (): void {
    config()->set('ai.default', 'openai');
});

it('returns a verdict for a valid judge response', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 0.95, "reason": "accurate and concise"}']);

    $judge = new Judge('claude-haiku-4-5', maxRetries: 1);

    $outcome = $judge->judge('output is concise', 'this is short');

    expect($outcome['verdict'])->toBeInstanceOf(JudgeVerdict::class);
    expect($outcome['verdict']->passed)->toBeTrue();
    expect($outcome['verdict']->score)->toBe(0.95);
    expect($outcome['verdict']->reason)->toBe('accurate and concise');
    expect($outcome['retryCount'])->toBe(0);
});

it('retries on malformed JSON and succeeds', function (): void {
    JudgeAgent::fake([
        'not json at all',
        '{"passed": false, "score": 0.3, "reason": "off-topic"}',
    ]);

    $judge = new Judge('claude-haiku-4-5', maxRetries: 1);

    $outcome = $judge->judge('be on-topic', 'random');

    expect($outcome['verdict']->passed)->toBeFalse();
    expect($outcome['verdict']->score)->toBe(0.3);
    expect($outcome['retryCount'])->toBe(1);
});

it('throws JudgeException when all retries fail', function (): void {
    JudgeAgent::fake([
        'junk one',
        'junk two',
    ]);

    $judge = new Judge('claude-haiku-4-5', maxRetries: 1);

    $judge->judge('x', 'y');
})->throws(JudgeException::class);

it('rejects score outside 0-1', function (): void {
    JudgeAgent::fake([
        '{"passed": true, "score": 1.5, "reason": "too high"}',
        '{"passed": true, "score": 2.0, "reason": "still too high"}',
    ]);

    $judge = new Judge('claude-haiku-4-5', maxRetries: 1);

    $judge->judge('x', 'y');
})->throws(JudgeException::class);

it('rejects missing keys in judge output', function (): void {
    JudgeAgent::fake([
        '{"passed": true, "reason": "no score"}',
        '{"score": 0.9, "reason": "no passed"}',
    ]);

    $judge = new Judge('claude-haiku-4-5', maxRetries: 1);

    $judge->judge('x', 'y');
})->throws(JudgeException::class);

it('uses the default judge model when no override is given', function (): void {
    $captured = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $model;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    $judge = new Judge('default-judge-model');

    $outcome = $judge->judge('x', 'y');

    expect($captured)->toBe('default-judge-model');
    expect($outcome['metadata']['judge_model'])->toBe('default-judge-model');
});

it('uses the override model when specified', function (): void {
    $captured = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $model;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    $judge = new Judge('default-judge-model');

    $outcome = $judge->judge('x', 'y', null, 'override-model');

    expect($captured)->toBe('override-model');
    expect($outcome['metadata']['judge_model'])->toBe('override-model');
});

it('includes the criteria in the judge prompt', function (): void {
    $captured = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $prompt;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    $judge = new Judge('m');
    $judge->judge('output must mention bananas', 'bananas are yellow');

    expect($captured)->toContain('output must mention bananas');
});

it('includes the input when provided', function (): void {
    $captured = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $prompt;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    $judge = new Judge('m');
    $judge->judge('criterion', 'answer', input: 'the question was about X');

    expect($captured)->toContain('the question was about X');
    expect($captured)->toContain('INPUT');
});

it('omits the input section when input is null', function (): void {
    $captured = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $prompt;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    $judge = new Judge('m');
    $judge->judge('criterion', 'answer');

    expect($captured)->not->toContain('INPUT');
});

it('includes token usage in metadata when the SDK reports it', function (): void {
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model): TextResponse {
        return new TextResponse(
            '{"passed": true, "score": 1.0, "reason": "ok"}',
            new Usage(promptTokens: 17, completionTokens: 5),
            new Meta($provider->name(), $model),
        );
    });

    $judge = new Judge('m');

    $outcome = $judge->judge('c', 'o');

    expect($outcome['metadata']['judge_tokens_in'])->toBe(17);
    expect($outcome['metadata']['judge_tokens_out'])->toBe(5);
});

it('leaves cost_usd as null', function (): void {
    JudgeAgent::fake(['{"passed": true, "score": 1.0, "reason": "ok"}']);

    $judge = new Judge('m');

    $outcome = $judge->judge('c', 'o');

    expect($outcome['metadata']['judge_cost_usd'])->toBeNull();
});

it('includes the raw response in metadata', function (): void {
    $raw = '{"passed": true, "score": 1.0, "reason": "ok"}';
    JudgeAgent::fake([$raw]);

    $judge = new Judge('m');

    $outcome = $judge->judge('c', 'o');

    expect($outcome['metadata']['judge_raw_response'])->toBe($raw);
});

it('stringifies non-string outputs when injecting into the prompt', function (): void {
    $captured = null;
    JudgeAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $prompt;

        return '{"passed": true, "score": 1.0, "reason": "ok"}';
    });

    $judge = new Judge('m');
    $judge->judge('criterion', ['foo' => 'bar']);

    expect($captured)->toContain('"foo"');
    expect($captured)->toContain('"bar"');
});
