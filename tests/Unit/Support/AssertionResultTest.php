<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Support\AssertionResult;

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

it('rejects scores below 0', function (): void {
    AssertionResult::pass('bad', -0.01);
})->throws(InvalidArgumentException::class, 'Score must be between 0.0 and 1.0');

it('rejects scores above 1', function (): void {
    AssertionResult::fail('bad', 1.01);
})->throws(InvalidArgumentException::class, 'Score must be between 0.0 and 1.0');

it('accepts score 0 and score 1 as boundaries', function (): void {
    expect(AssertionResult::pass('zero', 0.0)->score)->toBe(0.0);
    expect(AssertionResult::pass('one', 1.0)->score)->toBe(1.0);
    expect(AssertionResult::fail('zero', 0.0)->score)->toBe(0.0);
    expect(AssertionResult::fail('one', 1.0)->score)->toBe(1.0);
});

it('is immutable', function (): void {
    $result = AssertionResult::pass('immutable');

    expect(fn () => (function () use ($result): void {
        /** @phpstan-ignore-next-line */
        $result->passed = false;
    })())->toThrow(Error::class);
});
