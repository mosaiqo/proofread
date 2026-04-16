<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Proofread;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    Proofread::registerPestExpectations();
});

/**
 * @param  list<float|int|null>  $costs
 */
function proofread_make_run_with_costs(array $costs): EvalRun
{
    $cases = array_map(fn (int $i): array => ['input' => 'case-'.$i], array_keys($costs));
    $dataset = Dataset::make('cost-dataset', $cases);

    $results = [];
    foreach ($costs as $i => $cost) {
        $metadata = $cost === null ? [] : ['cost_usd' => $cost];
        $assertion = AssertionResult::pass('ok', null, $metadata);
        $results[] = EvalResult::make(['input' => 'case-'.$i], 'output-'.$i, [$assertion], 1.0);
    }

    return EvalRun::make($dataset, $results, 10.0);
}

it('passes when total cost is under the limit', function (): void {
    $run = proofread_make_run_with_costs([0.001, 0.002, 0.003]);

    expect($run)->toCostUnder(0.01);
});

it('passes when total cost equals the limit', function (): void {
    $run = proofread_make_run_with_costs([0.005, 0.005]);

    expect($run)->toCostUnder(0.01);
});

it('fails when total cost exceeds the limit', function (): void {
    $run = proofread_make_run_with_costs([0.01, 0.02]);

    $caught = null;
    try {
        expect($run)->toCostUnder(0.015);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('exceeds limit');
    expect($caught->getMessage())->toContain('0.0300');
    expect($caught->getMessage())->toContain('0.0150');
});

it('fails with a clear message when no result has cost tracking', function (): void {
    $run = proofread_make_run_with_costs([null, null, null]);

    $caught = null;
    try {
        expect($run)->toCostUnder(0.05);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('No cost tracking');
});

it('treats null cost_usd as 0 only when at least one result reports cost', function (): void {
    $run = proofread_make_run_with_costs([0.002, null, 0.003]);

    expect($run)->toCostUnder(0.01);
});

it('rejects non-EvalRun subjects', function (): void {
    $caught = null;
    try {
        expect('not-a-run')->toCostUnder(0.01);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('EvalRun');
});

it('supports negation', function (): void {
    $run = proofread_make_run_with_costs([0.05, 0.05]);

    expect($run)->not->toCostUnder(0.01);
});

it('formats the cost in the message with 4 decimal USD precision', function (): void {
    $run = proofread_make_run_with_costs([0.12345]);

    $caught = null;
    try {
        expect($run)->toCostUnder(0.01);
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('$0.1235');
    expect($caught->getMessage())->toContain('$0.0100');
});
