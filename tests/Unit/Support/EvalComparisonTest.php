<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalComparison;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;

function comparisonRun(Dataset $dataset, bool $passed, float $cost = 0.01): EvalRun
{
    $result = EvalResult::make(
        ['input' => 'x'],
        'y',
        [
            $passed
                ? AssertionResult::pass('ok', null, ['assertion_name' => 'stub', 'cost_usd' => $cost])
                : AssertionResult::fail('nope', null, ['assertion_name' => 'stub', 'cost_usd' => $cost]),
        ],
        5.0,
    );

    return EvalRun::make($dataset, [$result], 10.0);
}

it('constructs a comparison with a runs map', function (): void {
    $dataset = Dataset::make('cmp', [['input' => 'a']]);
    $runs = [
        'haiku' => comparisonRun($dataset, true),
        'sonnet' => comparisonRun($dataset, true),
    ];

    $comparison = EvalComparison::make('my-comparison', $dataset, $runs, 123.4);

    expect($comparison->name)->toBe('my-comparison')
        ->and($comparison->dataset)->toBe($dataset)
        ->and($comparison->runs)->toBe($runs)
        ->and($comparison->durationMs)->toBe(123.4);
});

it('passed() is true when every run passed', function (): void {
    $dataset = Dataset::make('cmp', [['input' => 'a']]);
    $comparison = EvalComparison::make('c', $dataset, [
        'haiku' => comparisonRun($dataset, true),
        'sonnet' => comparisonRun($dataset, true),
    ], 10.0);

    expect($comparison->passed())->toBeTrue();
});

it('passed() is false when any run failed', function (): void {
    $dataset = Dataset::make('cmp', [['input' => 'a']]);
    $comparison = EvalComparison::make('c', $dataset, [
        'haiku' => comparisonRun($dataset, true),
        'sonnet' => comparisonRun($dataset, false),
    ], 10.0);

    expect($comparison->passed())->toBeFalse();
});

it('preserves subject label order in subjectLabels()', function (): void {
    $dataset = Dataset::make('cmp', [['input' => 'a']]);
    $comparison = EvalComparison::make('c', $dataset, [
        'haiku' => comparisonRun($dataset, true),
        'sonnet' => comparisonRun($dataset, true),
        'opus' => comparisonRun($dataset, true),
    ], 10.0);

    expect($comparison->subjectLabels())->toBe(['haiku', 'sonnet', 'opus']);
});

it('runForSubject() returns the run for a given label or null', function (): void {
    $dataset = Dataset::make('cmp', [['input' => 'a']]);
    $haiku = comparisonRun($dataset, true);
    $comparison = EvalComparison::make('c', $dataset, [
        'haiku' => $haiku,
    ], 10.0);

    expect($comparison->runForSubject('haiku'))->toBe($haiku)
        ->and($comparison->runForSubject('sonnet'))->toBeNull();
});

it('passRates() returns a map of label to pass rate', function (): void {
    $dataset = Dataset::make('cmp', [['input' => 'a']]);
    $comparison = EvalComparison::make('c', $dataset, [
        'haiku' => comparisonRun($dataset, true),
        'sonnet' => comparisonRun($dataset, false),
    ], 10.0);

    expect($comparison->passRates())->toBe([
        'haiku' => 1.0,
        'sonnet' => 0.0,
    ]);
});

it('totalCosts() sums cost_usd per subject', function (): void {
    $dataset = Dataset::make('cmp', [['input' => 'a']]);
    $comparison = EvalComparison::make('c', $dataset, [
        'haiku' => comparisonRun($dataset, true, 0.02),
        'sonnet' => comparisonRun($dataset, true, 0.05),
    ], 10.0);

    expect($comparison->totalCosts())->toBe([
        'haiku' => 0.02,
        'sonnet' => 0.05,
    ]);
});

it('totalCosts() returns null for a subject when no case reports cost', function (): void {
    $dataset = Dataset::make('cmp', [['input' => 'a']]);
    $noCost = EvalResult::make(
        ['input' => 'x'],
        'y',
        [AssertionResult::pass('ok', null, ['assertion_name' => 'stub'])],
        5.0,
    );
    $run = EvalRun::make($dataset, [$noCost], 10.0);

    $comparison = EvalComparison::make('c', $dataset, [
        'haiku' => $run,
        'sonnet' => comparisonRun($dataset, true, 0.03),
    ], 10.0);

    expect($comparison->totalCosts())->toBe([
        'haiku' => null,
        'sonnet' => 0.03,
    ]);
});

it('rejects an empty runs map', function (): void {
    $dataset = Dataset::make('cmp', [['input' => 'a']]);

    expect(fn () => EvalComparison::make('c', $dataset, [], 10.0))
        ->toThrow(InvalidArgumentException::class, 'runs');
});

it('rejects non-string keys in runs map', function (): void {
    $dataset = Dataset::make('cmp', [['input' => 'a']]);
    $run = comparisonRun($dataset, true);

    expect(fn () => EvalComparison::make('c', $dataset, [0 => $run], 10.0))
        ->toThrow(InvalidArgumentException::class, 'string');
});

it('rejects empty string keys in runs map', function (): void {
    $dataset = Dataset::make('cmp', [['input' => 'a']]);
    $run = comparisonRun($dataset, true);

    expect(fn () => EvalComparison::make('c', $dataset, ['' => $run], 10.0))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects runs that are not EvalRun instances', function (): void {
    $dataset = Dataset::make('cmp', [['input' => 'a']]);

    expect(fn () => EvalComparison::make('c', $dataset, ['haiku' => 'not a run'], 10.0))
        ->toThrow(InvalidArgumentException::class, 'EvalRun');
});

it('rejects a negative duration', function (): void {
    $dataset = Dataset::make('cmp', [['input' => 'a']]);
    $run = comparisonRun($dataset, true);

    expect(fn () => EvalComparison::make('c', $dataset, ['haiku' => $run], -1.0))
        ->toThrow(InvalidArgumentException::class, 'Duration');
});
