<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Proofread;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    Proofread::registerPestExpectations();
});

it('passes when the assertion passes', function (): void {
    expect('foobar')->toPassAssertion(ContainsAssertion::make('foo'));
});

it('fails with a readable message when the assertion fails', function (): void {
    $caught = null;
    try {
        expect('foobar')->toPassAssertion(ContainsAssertion::make('baz'));
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('contains');
    expect($caught->getMessage())->toContain('Output does not contain "baz"');
});

it('supports negation', function (): void {
    expect('foobar')->not->toPassAssertion(ContainsAssertion::make('baz'));
});
