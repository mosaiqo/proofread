<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\LengthAssertion;
use Mosaiqo\Proofread\Contracts\Assertion;

it('passes when the length is at least the minimum', function (): void {
    $result = LengthAssertion::min(3)->run('hello');

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Output length 5 is within bounds');
});

it('fails when the length is below the minimum', function (): void {
    $result = LengthAssertion::min(10)->run('hello');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Output length 5 is below minimum 10');
});

it('passes when the length is at most the maximum', function (): void {
    $result = LengthAssertion::max(10)->run('hello');

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Output length 5 is within bounds');
});

it('fails when the length exceeds the maximum', function (): void {
    $result = LengthAssertion::max(3)->run('hello');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Output length 5 exceeds maximum 3');
});

it('passes when the length is within the range', function (): void {
    $result = LengthAssertion::between(3, 10)->run('hello');

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Output length 5 is within bounds');
});

it('fails when the length is below the range', function (): void {
    $result = LengthAssertion::between(10, 20)->run('hello');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Output length 5 is below minimum 10');
});

it('fails when the length is above the range', function (): void {
    $result = LengthAssertion::between(1, 3)->run('hello');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Output length 5 exceeds maximum 3');
});

it('counts multibyte characters correctly', function (): void {
    $emoji = 'hi👋';
    expect(mb_strlen($emoji))->toBe(3);

    $result = LengthAssertion::between(3, 3)->run($emoji);
    expect($result->passed)->toBeTrue();

    $accented = 'café';
    expect(mb_strlen($accented))->toBe(4);

    $accentedResult = LengthAssertion::between(4, 4)->run($accented);
    expect($accentedResult->passed)->toBeTrue();
});

it('rejects negative minimums', function (): void {
    LengthAssertion::min(-1);
})->throws(InvalidArgumentException::class);

it('rejects negative maximums', function (): void {
    LengthAssertion::max(-1);
})->throws(InvalidArgumentException::class);

it('rejects ranges where min exceeds max', function (): void {
    LengthAssertion::between(10, 5);
})->throws(InvalidArgumentException::class);

it('fails when the output is not a string', function (): void {
    $assertion = LengthAssertion::min(1);

    $arrayResult = $assertion->run(['foo']);
    expect($arrayResult->passed)->toBeFalse();
    expect($arrayResult->reason)->toBe('Expected string output, got array');

    $intResult = $assertion->run(42);
    expect($intResult->passed)->toBeFalse();
    expect($intResult->reason)->toBe('Expected string output, got integer');

    $nullResult = $assertion->run(null);
    expect($nullResult->passed)->toBeFalse();
    expect($nullResult->reason)->toBe('Expected string output, got NULL');
});

it('exposes its name as "length"', function (): void {
    expect(LengthAssertion::min(1)->name())->toBe('length');
});

it('implements the Assertion contract', function (): void {
    expect(LengthAssertion::min(1))->toBeInstanceOf(Assertion::class);
});
