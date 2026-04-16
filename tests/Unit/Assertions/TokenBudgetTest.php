<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\TokenBudget;
use Mosaiqo\Proofread\Contracts\Assertion;

it('passes when input tokens are under the limit', function (): void {
    $result = TokenBudget::maxInput(1000)->run('x', ['tokens_in' => 800]);

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Input tokens 800 within limit of 1000');
});

it('passes when input tokens equal the limit', function (): void {
    $result = TokenBudget::maxInput(1000)->run('x', ['tokens_in' => 1000]);

    expect($result->passed)->toBeTrue();
});

it('fails when input tokens exceed the limit', function (): void {
    $result = TokenBudget::maxInput(1000)->run('x', ['tokens_in' => 1200]);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Input tokens 1200 exceed limit of 1000');
});

it('fails when tokens_in is missing', function (): void {
    $result = TokenBudget::maxInput(1000)->run('x', []);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe("TokenBudget requires 'tokens_in' in context");
});

it('passes when output tokens are under the limit', function (): void {
    $result = TokenBudget::maxOutput(500)->run('x', ['tokens_out' => 400]);

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Output tokens 400 within limit of 500');
});

it('fails when output tokens exceed the limit', function (): void {
    $result = TokenBudget::maxOutput(500)->run('x', ['tokens_out' => 600]);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Output tokens 600 exceed limit of 500');
});

it('fails when tokens_out is missing', function (): void {
    $result = TokenBudget::maxOutput(500)->run('x', []);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe("TokenBudget requires 'tokens_out' in context");
});

it('passes when total tokens are under the limit using the pair', function (): void {
    $result = TokenBudget::maxTotal(1500)->run('x', [
        'tokens_in' => 800,
        'tokens_out' => 400,
    ]);

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Total tokens 1200 within limit of 1500');
});

it('fails when total exceeds the limit', function (): void {
    $result = TokenBudget::maxTotal(1500)->run('x', [
        'tokens_in' => 1000,
        'tokens_out' => 800,
    ]);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe('Total tokens 1800 exceed limit of 1500');
});

it('uses tokens_total from context when present instead of summing', function (): void {
    $result = TokenBudget::maxTotal(1500)->run('x', [
        'tokens_total' => 900,
        // Ignored because tokens_total takes precedence:
        'tokens_in' => 99_999,
        'tokens_out' => 99_999,
    ]);

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toBe('Total tokens 900 within limit of 1500');
});

it('fails when neither tokens_total nor the pair are in context', function (): void {
    $result = TokenBudget::maxTotal(1500)->run('x', []);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe("TokenBudget requires 'tokens_total' or both 'tokens_in' and 'tokens_out' in context");
});

it('rejects negative limits on maxInput', function (): void {
    TokenBudget::maxInput(-1);
})->throws(InvalidArgumentException::class);

it('rejects negative limits on maxOutput', function (): void {
    TokenBudget::maxOutput(-1);
})->throws(InvalidArgumentException::class);

it('rejects negative limits on maxTotal', function (): void {
    TokenBudget::maxTotal(-1);
})->throws(InvalidArgumentException::class);

it('exposes its name as "token_budget"', function (): void {
    expect(TokenBudget::maxInput(100)->name())->toBe('token_budget');
});

it('implements the Assertion contract', function (): void {
    expect(TokenBudget::maxInput(100))->toBeInstanceOf(Assertion::class);
});

it('fails when tokens_in is non-integer', function (): void {
    $result = TokenBudget::maxInput(100)->run('x', ['tokens_in' => 'many']);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe("TokenBudget requires integer 'tokens_in' in context, got string");
});

it('fails when tokens_in is null (SDK did not provide it)', function (): void {
    $result = TokenBudget::maxInput(100)->run('x', ['tokens_in' => null]);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toBe("TokenBudget requires 'tokens_in' in context; got null (subject may not report token usage)");
});
