<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\LatencyLimit;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

it('passes when latency is under the limit', function (): void {
    $result = LatencyLimit::under(100)->run('x', ['latency_ms' => 45.67]);

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Latency 45.67ms is within limit of 100ms');
});

it('passes when latency equals the limit', function (): void {
    $result = LatencyLimit::under(100)->run('x', ['latency_ms' => 100.0]);

    expect($result->passed)->toBeTrue();
});

it('fails when latency exceeds the limit', function (): void {
    $result = LatencyLimit::under(100)->run('x', ['latency_ms' => 123.456]);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Latency 123.46ms exceeds limit of 100ms');
});

it('fails when latency_ms is missing from the context', function (): void {
    $result = LatencyLimit::under(100)->run('x', []);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe("LatencyLimit requires 'latency_ms' in context; runner may not be populating it");
});

it('fails when latency_ms is non-numeric', function (): void {
    $result = LatencyLimit::under(100)->run('x', ['latency_ms' => 'slow']);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe("LatencyLimit requires numeric 'latency_ms' in context, got string");
});

it('rejects a non-positive limit via under(0)', function (): void {
    LatencyLimit::under(0);
})->throws(InvalidArgumentException::class);

it('rejects a non-positive limit via under negative', function (): void {
    LatencyLimit::under(-10);
})->throws(InvalidArgumentException::class);

it('formats the reason with the latency and limit', function (): void {
    $pass = LatencyLimit::under(200)->run('x', ['latency_ms' => 12.3]);
    expect($pass->reason)->toContain('12.3');
    expect($pass->reason)->toContain('200');

    $fail = LatencyLimit::under(50)->run('x', ['latency_ms' => 88.8]);
    expect($fail->reason)->toContain('88.8');
    expect($fail->reason)->toContain('50');
});

it('exposes its name as "latency_limit"', function (): void {
    expect(LatencyLimit::under(100)->name())->toBe('latency_limit');
});

it('implements the Assertion contract', function (): void {
    expect(LatencyLimit::under(100))->toBeInstanceOf(Assertion::class);
});

it('returns an AssertionResult', function (): void {
    expect(LatencyLimit::under(100)->run('x', ['latency_ms' => 1.0]))
        ->toBeInstanceOf(AssertionResult::class);
});
