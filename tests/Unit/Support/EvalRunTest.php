<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;

/**
 * @param  array<string, mixed>  $case
 */
function passingResult(array $case = ['input' => 'x']): EvalResult
{
    return EvalResult::make($case, 'x', [AssertionResult::pass('ok')], 0.1);
}

/**
 * @param  array<string, mixed>  $case
 */
function failingResult(array $case = ['input' => 'x']): EvalResult
{
    return EvalResult::make($case, 'x', [AssertionResult::fail('nope')], 0.1);
}

it('creates a run with a dataset, results and duration', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a'], ['input' => 'b']]);
    $run = EvalRun::make($dataset, [passingResult(), passingResult()], 5.0);

    expect($run->dataset)->toBe($dataset);
    expect($run->results)->toHaveCount(2);
    expect($run->durationMs)->toBe(5.0);
});

it('reports the total number of results', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a'], ['input' => 'b']]);
    $run = EvalRun::make($dataset, [passingResult(), failingResult()], 1.0);

    expect($run->total())->toBe(2);
});

it('reports passed count', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a'], ['input' => 'b'], ['input' => 'c']]);
    $run = EvalRun::make($dataset, [passingResult(), failingResult(), passingResult()], 1.0);

    expect($run->passedCount())->toBe(2);
});

it('reports failed count', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a'], ['input' => 'b'], ['input' => 'c']]);
    $run = EvalRun::make($dataset, [passingResult(), failingResult(), passingResult()], 1.0);

    expect($run->failedCount())->toBe(1);
});

it('is passed when all results passed', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a'], ['input' => 'b']]);
    $run = EvalRun::make($dataset, [passingResult(), passingResult()], 1.0);

    expect($run->passed())->toBeTrue();
    expect($run->failed())->toBeFalse();
});

it('is failed when any result failed', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a'], ['input' => 'b']]);
    $run = EvalRun::make($dataset, [passingResult(), failingResult()], 1.0);

    expect($run->passed())->toBeFalse();
    expect($run->failed())->toBeTrue();
});

it('returns only the failed results via failures()', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a'], ['input' => 'b'], ['input' => 'c']]);
    $passed = passingResult(['input' => 'a']);
    $failed = failingResult(['input' => 'b']);
    $passed2 = passingResult(['input' => 'c']);
    $run = EvalRun::make($dataset, [$passed, $failed, $passed2], 1.0);

    expect($run->failures())->toHaveCount(1);
    expect($run->failures()[0])->toBe($failed);
});

it('preserves the order of failures', function (): void {
    $dataset = Dataset::make('d', [
        ['input' => 'a'], ['input' => 'b'], ['input' => 'c'], ['input' => 'd'],
    ]);
    $a = failingResult(['input' => 'a']);
    $b = passingResult(['input' => 'b']);
    $c = failingResult(['input' => 'c']);
    $d = failingResult(['input' => 'd']);
    $run = EvalRun::make($dataset, [$a, $b, $c, $d], 1.0);

    expect($run->failures())->toBe([$a, $c, $d]);
});

it('computes pass rate as a ratio between 0 and 1', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a'], ['input' => 'b'], ['input' => 'c'], ['input' => 'd']]);
    $run = EvalRun::make($dataset, [
        passingResult(), passingResult(), passingResult(), failingResult(),
    ], 1.0);

    expect($run->passRate())->toBe(0.75);
});

it('returns pass rate 1.0 for empty results', function (): void {
    $dataset = Dataset::make('d', []);
    $run = EvalRun::make($dataset, [], 0.0);

    expect($run->passRate())->toBe(1.0);
});

it('trivially passes with empty results', function (): void {
    $dataset = Dataset::make('d', []);
    $run = EvalRun::make($dataset, [], 0.0);

    expect($run->passed())->toBeTrue();
    expect($run->total())->toBe(0);
});

it('rejects non-EvalResult items', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a']]);
    EvalRun::make($dataset, ['not-a-result'], 0.0);
})->throws(InvalidArgumentException::class);

it('rejects a negative duration', function (): void {
    $dataset = Dataset::make('d', []);
    EvalRun::make($dataset, [], -0.5);
})->throws(InvalidArgumentException::class);

it('rejects more results than the dataset has cases', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a']]);
    EvalRun::make($dataset, [passingResult(), passingResult()], 0.0);
})->throws(InvalidArgumentException::class);
