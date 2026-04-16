<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Mosaiqo\Proofread\Assertions\CountAssertion;
use Mosaiqo\Proofread\Contracts\Assertion;

it('passes when count equals the expected value', function (): void {
    $result = CountAssertion::equals(3)->run([1, 2, 3]);

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Count 3 is within bounds');
});

it('fails when count does not equal the expected value', function (): void {
    $result = CountAssertion::equals(3)->run([1, 2]);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Count 2 is below minimum 3');
});

it('passes when count meets the minimum', function (): void {
    $result = CountAssertion::atLeast(2)->run([1, 2, 3]);

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Count 3 is within bounds');
});

it('fails when count is below the minimum', function (): void {
    $result = CountAssertion::atLeast(5)->run([1, 2, 3]);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Count 3 is below minimum 5');
});

it('passes when count is at most the maximum', function (): void {
    $result = CountAssertion::atMost(5)->run([1, 2, 3]);

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Count 3 is within bounds');
});

it('fails when count exceeds the maximum', function (): void {
    $result = CountAssertion::atMost(2)->run([1, 2, 3]);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Count 3 exceeds maximum 2');
});

it('passes when count is within the range', function (): void {
    $result = CountAssertion::between(2, 5)->run([1, 2, 3]);

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Count 3 is within bounds');
});

it('fails when count is below the range', function (): void {
    $result = CountAssertion::between(5, 10)->run([1, 2, 3]);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Count 3 is below minimum 5');
});

it('fails when count is above the range', function (): void {
    $result = CountAssertion::between(1, 2)->run([1, 2, 3]);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Count 3 exceeds maximum 2');
});

it('counts items in a Countable instance', function (): void {
    $collection = new Collection(['a', 'b', 'c', 'd']);

    $result = CountAssertion::equals(4)->run($collection);

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Count 4 is within bounds');
});

it('counts items in an array', function (): void {
    $result = CountAssertion::equals(0)->run([]);

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Count 0 is within bounds');
});

it('fails when output is not countable', function (): void {
    $assertion = CountAssertion::equals(1);

    $intResult = $assertion->run(42);
    expect($intResult->passed)->toBeFalse();
    expect($intResult->reason)->toBe('CountAssertion requires array or Countable output, got integer');

    $stringResult = $assertion->run('hello');
    expect($stringResult->passed)->toBeFalse();
    expect($stringResult->reason)->toBe('CountAssertion requires array or Countable output, got string');

    $nullResult = $assertion->run(null);
    expect($nullResult->passed)->toBeFalse();
    expect($nullResult->reason)->toBe('CountAssertion requires array or Countable output, got NULL');
});

it('rejects negative counts', function (): void {
    expect(fn () => CountAssertion::equals(-1))->toThrow(InvalidArgumentException::class);
    expect(fn () => CountAssertion::atLeast(-5))->toThrow(InvalidArgumentException::class);
    expect(fn () => CountAssertion::atMost(-3))->toThrow(InvalidArgumentException::class);
    expect(fn () => CountAssertion::between(-1, 3))->toThrow(InvalidArgumentException::class);
    expect(fn () => CountAssertion::between(1, -3))->toThrow(InvalidArgumentException::class);
});

it('rejects ranges where min exceeds max', function (): void {
    CountAssertion::between(10, 5);
})->throws(InvalidArgumentException::class);

it('exposes its name as "count"', function (): void {
    expect(CountAssertion::equals(1)->name())->toBe('count');
});

it('implements the Assertion contract', function (): void {
    expect(CountAssertion::equals(1))->toBeInstanceOf(Assertion::class);
});
