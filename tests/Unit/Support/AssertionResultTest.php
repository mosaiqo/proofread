<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\JudgeResult;

/**
 * Rogue fixture class that attempts to extend AssertionResult outside
 * the allowed list. Constructing an instance must throw.
 */
final class RogueAssertionResult extends AssertionResult
{
    public static function forge(): self
    {
        return new self(true, 'rogue');
    }
}

it('creates a passed result with reason and no score', function (): void {
    $result = AssertionResult::pass('All good');

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('All good');
    expect($result->score)->toBeNull();
});

it('creates a passed result with a score', function (): void {
    $result = AssertionResult::pass('Nice', 0.75);

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Nice');
    expect($result->score)->toBe(0.75);
});

it('creates a failed result with reason', function (): void {
    $result = AssertionResult::fail('Missing needle');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Missing needle');
    expect($result->score)->toBeNull();
});

it('creates a failed result with a score', function (): void {
    $result = AssertionResult::fail('Partial match', 0.25);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Partial match');
    expect($result->score)->toBe(0.25);
});

it('accepts any float as score', function (): void {
    expect(AssertionResult::pass('neg', -5.0)->score)->toBe(-5.0);
    expect(AssertionResult::pass('neg-one', -1.0)->score)->toBe(-1.0);
    expect(AssertionResult::pass('zero', 0.0)->score)->toBe(0.0);
    expect(AssertionResult::pass('half', 0.5)->score)->toBe(0.5);
    expect(AssertionResult::pass('one', 1.0)->score)->toBe(1.0);
    expect(AssertionResult::fail('big', 100.5)->score)->toBe(100.5);
    expect(AssertionResult::fail('neg', -0.01)->score)->toBe(-0.01);
    expect(AssertionResult::fail('over', 1.01)->score)->toBe(1.01);
});

it('accepts null score', function (): void {
    expect(AssertionResult::pass('no-score')->score)->toBeNull();
    expect(AssertionResult::fail('no-score')->score)->toBeNull();
});

it('is immutable', function (): void {
    $result = AssertionResult::pass('immutable');

    expect(fn () => (function () use ($result): void {
        /** @phpstan-ignore-next-line */
        $result->passed = false;
    })())->toThrow(Error::class);
});

it('defaults metadata to an empty array', function (): void {
    expect(AssertionResult::pass()->metadata)->toBe([]);
    expect(AssertionResult::fail('nope')->metadata)->toBe([]);
});

it('accepts metadata on pass', function (): void {
    $result = AssertionResult::pass('ok', 0.9, ['tokens' => 10, 'model' => 'x']);

    expect($result->metadata)->toBe(['tokens' => 10, 'model' => 'x']);
});

it('accepts metadata on fail', function (): void {
    $result = AssertionResult::fail('bad', 0.2, ['retry_count' => 2]);

    expect($result->metadata)->toBe(['retry_count' => 2]);
});

it('preserves metadata shape unchanged', function (): void {
    $nested = [
        'usage' => ['tokens_in' => 5, 'tokens_out' => 7],
        'raw' => 'verbatim',
        'flags' => [true, false, null],
    ];

    $result = AssertionResult::pass('shape', null, $nested);

    expect($result->metadata)->toBe($nested);
});

it('allows JudgeResult as a subclass', function (): void {
    $result = JudgeResult::pass(
        reason: 'excellent',
        score: 0.95,
        metadata: [],
        judgeModel: 'claude-haiku-4-5',
        retryCount: 0,
    );

    expect($result)->toBeInstanceOf(JudgeResult::class);
    expect($result)->toBeInstanceOf(AssertionResult::class);
    expect($result->passed)->toBeTrue();
});

it('rejects unknown subclasses', function (): void {
    expect(fn (): AssertionResult => RogueAssertionResult::forge())->toThrow(LogicException::class);
});
