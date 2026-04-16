<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\JudgeResult;

it('passes with score, model and reason', function (): void {
    $result = JudgeResult::pass('looks good', 0.92, [], 'claude-haiku-4-5');

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('looks good');
    expect($result->score)->toBe(0.92);
    expect($result->judgeModel)->toBe('claude-haiku-4-5');
});

it('fails with reason, optional score and model', function (): void {
    $withScore = JudgeResult::fail('off-topic', 0.1, [], 'claude-haiku-4-5');
    $noScore = JudgeResult::fail('parse error', null, [], 'claude-haiku-4-5');

    expect($withScore->passed)->toBeFalse();
    expect($withScore->score)->toBe(0.1);
    expect($noScore->score)->toBeNull();
    expect($noScore->reason)->toBe('parse error');
});

it('defaults retryCount to zero', function (): void {
    $passed = JudgeResult::pass('ok', 1.0, [], 'm');
    $failed = JudgeResult::fail('bad', null, [], 'm');

    expect($passed->retryCount)->toBe(0);
    expect($failed->retryCount)->toBe(0);
});

it('preserves metadata', function (): void {
    $metadata = [
        'judge_tokens_in' => 42,
        'judge_tokens_out' => 17,
        'judge_cost_usd' => null,
    ];

    $result = JudgeResult::pass('ok', 0.9, $metadata, 'm');

    expect($result->metadata)->toBe($metadata);
});

it('rejects empty judge model', function (): void {
    JudgeResult::pass('ok', 1.0, [], '');
})->throws(InvalidArgumentException::class);

it('rejects empty judge model on fail', function (): void {
    JudgeResult::fail('bad', null, [], '');
})->throws(InvalidArgumentException::class);

it('rejects negative retry count', function (): void {
    JudgeResult::pass('ok', 1.0, [], 'm', -1);
})->throws(InvalidArgumentException::class);

it('rejects negative retry count on fail', function (): void {
    JudgeResult::fail('bad', null, [], 'm', -1);
})->throws(InvalidArgumentException::class);

it('rejects pass without a score', function (): void {
    JudgeResult::pass('ok', null, [], 'm');
})->throws(InvalidArgumentException::class);

it('is an AssertionResult', function (): void {
    $result = JudgeResult::pass('ok', 1.0, [], 'm');

    expect($result)->toBeInstanceOf(AssertionResult::class);
});

it('exposes judgeModel and retryCount as readonly', function (): void {
    $result = JudgeResult::pass('ok', 1.0, [], 'claude', 3);

    expect($result->judgeModel)->toBe('claude');
    expect($result->retryCount)->toBe(3);

    expect(fn () => (function () use ($result): void {
        /** @phpstan-ignore-next-line */
        $result->judgeModel = 'other';
    })())->toThrow(Error::class);
});

it('passes all metadata through to parent', function (): void {
    $metadata = ['judge_raw_response' => '{"passed":true,"score":1,"reason":"ok"}'];
    $result = JudgeResult::pass('ok', 1.0, $metadata, 'm');

    expect($result->metadata)->toBe($metadata);
});
