<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\PiiLeakageAssertion;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Shadow\PiiSanitizer;
use Mosaiqo\Proofread\Support\AssertionResult;

beforeEach(function (): void {
    app()->forgetInstance(PiiSanitizer::class);

    config()->set('proofread.shadow.sanitize', [
        'pii_keys' => ['email', 'phone', 'ssn', 'credit_card', 'password', 'api_key', 'token'],
        'redact_patterns' => [
            '/\b(?:\d[ -]*?){13,19}\b/' => '[CARD]',
            '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/' => '[EMAIL]',
        ],
        'max_input_length' => 2000,
        'max_output_length' => 5000,
        'redacted_placeholder' => '[REDACTED]',
    ]);
});

it('passes when output contains no PII', function (): void {
    $assertion = PiiLeakageAssertion::make();

    $result = $assertion->run('The quick brown fox jumps over the lazy dog.');

    expect($result->passed)->toBeTrue();
    expect($result->reason)->toContain('No PII');
});

it('fails when output contains an email address', function (): void {
    $assertion = PiiLeakageAssertion::make();

    $result = $assertion->run('Reach me at john.doe@example.com any time.');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('[EMAIL]');
});

it('fails when output contains a credit card number', function (): void {
    $assertion = PiiLeakageAssertion::make();

    $result = $assertion->run('Charge card 4111 1111 1111 1111 now.');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('[CARD]');
});

it('fails when output contains multiple PII types', function (): void {
    $assertion = PiiLeakageAssertion::make();

    $result = $assertion->run('Email a@b.com and card 4111 1111 1111 1111.');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('[EMAIL]');
    expect($result->reason)->toContain('[CARD]');
});

it('includes placeholders_found in metadata', function (): void {
    $assertion = PiiLeakageAssertion::make();

    $result = $assertion->run('Email: alice@example.com');

    expect($result->metadata)->toHaveKey('placeholders_found');
    expect($result->metadata['placeholders_found'])->toContain('[EMAIL]');
    expect($result->metadata)->toHaveKey('sanitized_output');
    expect($result->metadata['sanitized_output'])->toContain('[EMAIL]');
});

it('uses custom redact patterns when provided', function (): void {
    $assertion = PiiLeakageAssertion::withPatterns([
        '/secret-[A-Za-z0-9]+/' => '[SECRET]',
    ]);

    $result = $assertion->run('token is secret-abc123 for you');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('[SECRET]');

    $clean = $assertion->run('no secret here');
    expect($clean->passed)->toBeTrue();
});

it('fails when output is not a string', function (): void {
    $assertion = PiiLeakageAssertion::make();

    $result = $assertion->run(['not' => 'a string']);

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('string');
});

it('accepts an explicit sanitizer dependency', function (): void {
    $custom = new PiiSanitizer(
        redactPatterns: ['/\btop-secret\b/' => '[CLASSIFIED]'],
    );

    $assertion = PiiLeakageAssertion::make($custom);

    $result = $assertion->run('this is top-secret information');

    expect($result->passed)->toBeFalse();
    expect($result->reason)->toContain('[CLASSIFIED]');
});

it('exposes name as "pii_leakage"', function (): void {
    expect(PiiLeakageAssertion::make()->name())->toBe('pii_leakage');
});

it('implements the Assertion contract', function (): void {
    expect(PiiLeakageAssertion::make())->toBeInstanceOf(Assertion::class);
});

it('returns AssertionResult instances', function (): void {
    $assertion = PiiLeakageAssertion::make();

    expect($assertion->run('clean text'))->toBeInstanceOf(AssertionResult::class);
    expect($assertion->run('a@b.com'))->toBeInstanceOf(AssertionResult::class);
});
