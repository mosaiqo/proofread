<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Proofread;
use Mosaiqo\Proofread\Support\Dataset;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    Proofread::registerPestExpectations();
});

it('passes when all cases satisfy all assertions', function (): void {
    $dataset = Dataset::make('happy', [
        ['input' => 'foo bar'],
        ['input' => 'foo baz'],
        ['input' => 'foo qux'],
    ]);

    expect(fn (string $input): string => $input)->toPassEval($dataset, [
        ContainsAssertion::make('foo'),
    ]);
});

it('fails when at least one case fails an assertion', function (): void {
    $dataset = Dataset::make('mixed', [
        ['input' => 'foo bar'],
        ['input' => 'nothing'],
    ]);

    $caught = null;
    try {
        expect(fn (string $input): string => $input)->toPassEval($dataset, [
            ContainsAssertion::make('foo'),
        ]);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    expect($caught)->toBeInstanceOf(ExpectationFailedException::class);
});

it('includes the dataset name in the failure message', function (): void {
    $dataset = Dataset::make('my-dataset', [['input' => 'nothing']]);

    $caught = null;
    try {
        expect(fn (string $input): string => $input)->toPassEval($dataset, [
            ContainsAssertion::make('foo'),
        ]);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('my-dataset');
});

it('includes the failing case index in the failure message', function (): void {
    $dataset = Dataset::make('indexed', [
        ['input' => 'foo'],
        ['input' => 'bar'],
    ]);

    $caught = null;
    try {
        expect(fn (string $input): string => $input)->toPassEval($dataset, [
            ContainsAssertion::make('foo'),
        ]);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('[1]');
});

it('includes the failing assertion name and reason', function (): void {
    $dataset = Dataset::make('named', [['input' => 'bar']]);

    $caught = null;
    try {
        expect(fn (string $input): string => $input)->toPassEval($dataset, [
            ContainsAssertion::make('foo'),
        ]);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('contains');
    expect($caught->getMessage())->toContain('Output does not contain "foo"');
});

it('truncates long inputs in the failure message', function (): void {
    $longInput = str_repeat('abcdefghij', 20);
    $dataset = Dataset::make('long', [['input' => $longInput]]);

    $caught = null;
    try {
        expect(fn (string $input): string => $input)->toPassEval($dataset, [
            ContainsAssertion::make('MISSING'),
        ]);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('...');
    expect(mb_strlen($caught->getMessage()))->toBeLessThan(mb_strlen($longInput) + 300);
});

it('lists up to three failures and summarizes the rest', function (): void {
    $dataset = Dataset::make('many', [
        ['input' => 'no1'],
        ['input' => 'no2'],
        ['input' => 'no3'],
        ['input' => 'no4'],
        ['input' => 'no5'],
    ]);

    $caught = null;
    try {
        expect(fn (string $input): string => $input)->toPassEval($dataset, [
            ContainsAssertion::make('foo'),
        ]);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('[0]');
    expect($caught->getMessage())->toContain('[1]');
    expect($caught->getMessage())->toContain('[2]');
    expect($caught->getMessage())->not->toContain('[3]');
    expect($caught->getMessage())->toContain('and 2 more failures');
});

it('supports negation', function (): void {
    $dataset = Dataset::make('neg', [['input' => 'bar']]);

    expect(fn (string $input): string => $input)->not->toPassEval($dataset, [
        ContainsAssertion::make('foo'),
    ]);
});

it('fails the expectation with a clear message when the subject is not callable', function (): void {
    $dataset = Dataset::make('d', [['input' => 'x']]);

    $caught = null;
    try {
        expect('not-a-callable')->toPassEval($dataset, []);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('callable');
});
