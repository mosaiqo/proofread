<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\CostLimit;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

it('passes when cost is under the limit', function (): void {
    $result = CostLimit::under(0.01)->run('x', ['cost_usd' => 0.0045]);

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Cost $0.0045 is within limit of $0.0100');
});

it('passes when cost equals the limit', function (): void {
    $result = CostLimit::under(0.01)->run('x', ['cost_usd' => 0.01]);

    expect($result->passed)->toBeTrue();
});

it('fails when cost exceeds the limit', function (): void {
    $result = CostLimit::under(0.01)->run('x', ['cost_usd' => 0.0123]);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Cost $0.0123 exceeds limit of $0.0100');
});

it('fails when cost_usd is missing', function (): void {
    $result = CostLimit::under(0.01)->run('x', []);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe("CostLimit requires 'cost_usd' in context; subject may not be reporting cost");
});

it('fails when cost_usd is null', function (): void {
    $result = CostLimit::under(0.01)->run('x', ['cost_usd' => null]);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe("CostLimit requires 'cost_usd' in context; cost tracking not available for this subject");
});

it('fails when cost_usd is non-numeric', function (): void {
    $result = CostLimit::under(0.01)->run('x', ['cost_usd' => 'cheap']);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe("CostLimit requires numeric 'cost_usd' in context, got string");
});

it('rejects a non-positive limit at zero', function (): void {
    CostLimit::under(0);
})->throws(InvalidArgumentException::class);

it('rejects a non-positive limit negative', function (): void {
    CostLimit::under(-0.01);
})->throws(InvalidArgumentException::class);

it('formats the reason with 4 decimal USD precision', function (): void {
    $result = CostLimit::under(0.1234)->run('x', ['cost_usd' => 0.0567]);

    expect($result->reason)->toContain('$0.0567');
    expect($result->reason)->toContain('$0.1234');
});

it('exposes its name as "cost_limit"', function (): void {
    expect(CostLimit::under(0.01)->name())->toBe('cost_limit');
});

it('implements the Assertion contract', function (): void {
    expect(CostLimit::under(0.01))->toBeInstanceOf(Assertion::class);
});

it('returns an AssertionResult', function (): void {
    expect(CostLimit::under(0.01)->run('x', ['cost_usd' => 0.001]))
        ->toBeInstanceOf(AssertionResult::class);
});
