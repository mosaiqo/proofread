<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

it('passes when the output contains the needle', function (): void {
    $result = ContainsAssertion::make('foo')->run('foobar');

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Output contains "foo"');
});

it('fails when the output does not contain the needle', function (): void {
    $result = ContainsAssertion::make('baz')->run('foobar');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Output does not contain "baz"');
});

it('is case sensitive by default', function (): void {
    $result = ContainsAssertion::make('FOO')->run('foobar');

    expect($result->passed)->toBeFalse();
});

it('can be configured case insensitive', function (): void {
    $result = ContainsAssertion::make('FOO', caseSensitive: false)->run('foobar');

    expect($result->passed)->toBeTrue();
});

it('fails when the output is not a string', function (): void {
    $assertion = ContainsAssertion::make('foo');

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

it('passes when the needle is empty', function (): void {
    $result = ContainsAssertion::make('')->run('anything');

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Output contains ""');
});

it('exposes its name as "contains"', function (): void {
    expect(ContainsAssertion::make('foo')->name())->toBe('contains');
});

it('returns an AssertionResult instance', function (): void {
    expect(ContainsAssertion::make('foo')->run('foobar'))
        ->toBeInstanceOf(AssertionResult::class);
});

it('implements the Assertion contract', function (): void {
    expect(ContainsAssertion::make('foo'))->toBeInstanceOf(Assertion::class);
});
