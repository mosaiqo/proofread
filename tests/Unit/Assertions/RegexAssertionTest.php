<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\RegexAssertion;
use Mosaiqo\Proofread\Contracts\Assertion;

it('passes when the output matches the pattern', function (): void {
    $result = RegexAssertion::make('/foo/')->run('foobar');

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Output matches /foo/');
});

it('fails when the output does not match the pattern', function (): void {
    $result = RegexAssertion::make('/baz/')->run('foobar');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Output does not match /baz/');
});

it('respects regex flags like case insensitive', function (): void {
    $result = RegexAssertion::make('/foo/i')->run('FOO bar');

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Output matches /foo/i');
});

it('fails when the output is not a string', function (): void {
    $assertion = RegexAssertion::make('/foo/');

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

it('rejects invalid patterns at construction time', function (): void {
    RegexAssertion::make('no-delimiters');
})->throws(InvalidArgumentException::class, 'no-delimiters');

it('exposes its name as "regex"', function (): void {
    expect(RegexAssertion::make('/foo/')->name())->toBe('regex');
});

it('implements the Assertion contract', function (): void {
    expect(RegexAssertion::make('/foo/'))->toBeInstanceOf(Assertion::class);
});

it('includes the pattern in the reason', function (): void {
    $passReason = RegexAssertion::make('/hello.*world/')->run('hello beautiful world')->reason;
    expect($passReason)->toContain('/hello.*world/');

    $failReason = RegexAssertion::make('/hello.*world/')->run('no match')->reason;
    expect($failReason)->toContain('/hello.*world/');
});
