<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\EvalResult;

it('creates a passed result when all assertions pass', function (): void {
    $result = EvalResult::make(
        case: ['input' => 'hi'],
        output: 'hi',
        assertionResults: [
            AssertionResult::pass('ok'),
            AssertionResult::pass('also ok'),
        ],
        durationMs: 1.5,
    );

    expect($result->passed())->toBeTrue();
    expect($result->failed())->toBeFalse();
});

it('creates a failed result when any assertion fails', function (): void {
    $result = EvalResult::make(
        case: ['input' => 'hi'],
        output: 'hi',
        assertionResults: [
            AssertionResult::pass('ok'),
            AssertionResult::fail('nope'),
        ],
        durationMs: 1.0,
    );

    expect($result->passed())->toBeFalse();
    expect($result->failed())->toBeTrue();
});

it('creates a failed result when an error is present', function (): void {
    $result = EvalResult::make(
        case: ['input' => 'hi'],
        output: null,
        assertionResults: [],
        durationMs: 0.5,
        error: new RuntimeException('boom'),
    );

    expect($result->passed())->toBeFalse();
    expect($result->failed())->toBeTrue();
    expect($result->hasError())->toBeTrue();
});

it('reports a trivially passed result when there are no assertions and no error', function (): void {
    $result = EvalResult::make(
        case: ['input' => 'x'],
        output: 'anything',
        assertionResults: [],
        durationMs: 0.0,
    );

    expect($result->passed())->toBeTrue();
});

it('exposes the wrapped case', function (): void {
    $case = ['input' => 'hi', 'expected' => 'HI'];
    $result = EvalResult::make($case, 'HI', [], 0.0);

    expect($result->case)->toBe($case);
});

it('exposes the output', function (): void {
    $result = EvalResult::make(['input' => 1], 'hello', [], 0.0);

    expect($result->output)->toBe('hello');
});

it('exposes the duration in ms', function (): void {
    $result = EvalResult::make(['input' => 1], 'x', [], 12.345);

    expect($result->durationMs)->toBe(12.345);
});

it('rejects non-AssertionResult items in assertionResults', function (): void {
    EvalResult::make(['input' => 'x'], 'x', ['not-a-result'], 0.0);
})->throws(InvalidArgumentException::class);

it('rejects a negative duration', function (): void {
    EvalResult::make(['input' => 'x'], 'x', [], -0.1);
})->throws(InvalidArgumentException::class);

it('accepts zero duration', function (): void {
    $result = EvalResult::make(['input' => 'x'], 'x', [], 0.0);

    expect($result->durationMs)->toBe(0.0);
});

it('identifies when an error is present', function (): void {
    $withError = EvalResult::make(['input' => 'x'], null, [], 0.0, new RuntimeException('x'));
    $withoutError = EvalResult::make(['input' => 'x'], 'x', [], 0.0);

    expect($withError->hasError())->toBeTrue();
    expect($withoutError->hasError())->toBeFalse();
});

it('stores the error output as null', function (): void {
    $result = EvalResult::make(
        case: ['input' => 'x'],
        output: null,
        assertionResults: [],
        durationMs: 0.0,
        error: new RuntimeException('x'),
    );

    expect($result->output)->toBeNull();
    expect($result->error)->toBeInstanceOf(RuntimeException::class);
});
