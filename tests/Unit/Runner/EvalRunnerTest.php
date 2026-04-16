<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;

/**
 * @param  callable(mixed, array<string, mixed>): AssertionResult  $run
 */
function fakeAssertion(string $name, callable $run): Assertion
{
    return new class($name, $run) implements Assertion
    {
        /** @var callable(mixed, array<string, mixed>): AssertionResult */
        private $runner;

        /**
         * @param  callable(mixed, array<string, mixed>): AssertionResult  $runner
         */
        public function __construct(
            private readonly string $assertionName,
            callable $runner,
        ) {
            $this->runner = $runner;
        }

        public function run(mixed $output, array $context = []): AssertionResult
        {
            return ($this->runner)($output, $context);
        }

        public function name(): string
        {
            return $this->assertionName;
        }
    };
}

function explodingAssertion(string $name, string $message): Assertion
{
    return fakeAssertion($name, function () use ($message): AssertionResult {
        if ($message !== '') {
            throw new RuntimeException($message);
        }

        return AssertionResult::pass();
    });
}

it('runs a subject against every case in a dataset', function (): void {
    $runner = new EvalRunner;
    $dataset = Dataset::make('d', [
        ['input' => 'a'],
        ['input' => 'b'],
        ['input' => 'c'],
    ]);

    $run = $runner->run(fn (mixed $input): string => (string) $input, $dataset, []);

    expect($run->total())->toBe(3);
});

it('passes the case input to the subject', function (): void {
    $runner = new EvalRunner;
    $received = [];
    $dataset = Dataset::make('d', [
        ['input' => 'alpha'],
        ['input' => 'beta'],
    ]);

    $runner->run(
        function (mixed $input) use (&$received): string {
            $received[] = $input;

            return 'ok';
        },
        $dataset,
        [],
    );

    expect($received)->toBe(['alpha', 'beta']);
});

it('passes the full case as the second argument to the subject', function (): void {
    $runner = new EvalRunner;
    $cases = [];
    $dataset = Dataset::make('d', [
        ['input' => 'x', 'expected' => 'X', 'meta' => ['k' => 'v']],
    ]);

    $runner->run(
        function (mixed $input, array $case) use (&$cases): string {
            $cases[] = $case;

            return 'ok';
        },
        $dataset,
        [],
    );

    expect($cases[0])->toBe(['input' => 'x', 'expected' => 'X', 'meta' => ['k' => 'v']]);
});

it('runs each assertion against every output', function (): void {
    $runner = new EvalRunner;
    $calls = 0;
    $assertion = fakeAssertion('counter', function () use (&$calls): AssertionResult {
        $calls++;

        return AssertionResult::pass('ok');
    });
    $dataset = Dataset::make('d', [
        ['input' => 'a'],
        ['input' => 'b'],
        ['input' => 'c'],
    ]);

    $runner->run(fn (): string => 'x', $dataset, [$assertion]);

    expect($calls)->toBe(3);
});

it('aggregates assertion results per case', function (): void {
    $runner = new EvalRunner;
    $a = fakeAssertion('a', fn (): AssertionResult => AssertionResult::pass('a-ok'));
    $b = fakeAssertion('b', fn (): AssertionResult => AssertionResult::pass('b-ok'));
    $dataset = Dataset::make('d', [['input' => 1], ['input' => 2]]);

    $run = $runner->run(fn (): string => 'x', $dataset, [$a, $b]);

    foreach ($run->results as $result) {
        expect($result->assertionResults)->toHaveCount(2);
    }
});

it('captures exceptions thrown by the subject as an error on the result', function (): void {
    $runner = new EvalRunner;
    $dataset = Dataset::make('d', [['input' => 'boom']]);

    $run = $runner->run(function (): never {
        throw new RuntimeException('kaboom');
    }, $dataset, []);

    expect($run->results[0]->hasError())->toBeTrue();
    expect($run->results[0]->error?->getMessage())->toBe('kaboom');
    expect($run->results[0]->output)->toBeNull();
});

it('does not run assertions when the subject throws', function (): void {
    $runner = new EvalRunner;
    $calls = 0;
    $assertion = fakeAssertion('counter', function () use (&$calls): AssertionResult {
        $calls++;

        return AssertionResult::pass('ok');
    });
    $dataset = Dataset::make('d', [['input' => 'boom']]);

    $runner->run(function (): never {
        throw new RuntimeException('x');
    }, $dataset, [$assertion]);

    expect($calls)->toBe(0);
});

it('still runs remaining cases after one throws', function (): void {
    $runner = new EvalRunner;
    $dataset = Dataset::make('d', [
        ['input' => 'a'],
        ['input' => 'middle'],
        ['input' => 'c'],
    ]);

    $run = $runner->run(function (mixed $input): string {
        if ($input === 'middle') {
            throw new RuntimeException('middle-fail');
        }

        return (string) $input;
    }, $dataset, []);

    expect($run->total())->toBe(3);
    expect($run->results[0]->hasError())->toBeFalse();
    expect($run->results[1]->hasError())->toBeTrue();
    expect($run->results[2]->hasError())->toBeFalse();
});

it('captures exceptions thrown by an assertion as a failing AssertionResult', function (): void {
    $runner = new EvalRunner;
    $exploding = explodingAssertion('explodes', 'assertion-boom');
    $dataset = Dataset::make('d', [['input' => 'x']]);

    $run = $runner->run(fn (): string => 'x', $dataset, [$exploding]);

    $assertionResults = $run->results[0]->assertionResults;
    expect($assertionResults)->toHaveCount(1);
    expect($assertionResults[0]->passed)->toBeFalse();
    expect($assertionResults[0]->reason)->toContain('explodes');
    expect($assertionResults[0]->reason)->toContain('assertion-boom');
});

it('continues running remaining assertions when one throws', function (): void {
    $runner = new EvalRunner;
    $bad = explodingAssertion('bad', 'bad');
    $good = fakeAssertion('good', fn (): AssertionResult => AssertionResult::pass('good-ok'));
    $dataset = Dataset::make('d', [['input' => 'x']]);

    $run = $runner->run(fn (): string => 'x', $dataset, [$bad, $good]);

    $assertionResults = $run->results[0]->assertionResults;
    expect($assertionResults)->toHaveCount(2);
    expect($assertionResults[0]->passed)->toBeFalse();
    expect($assertionResults[1]->passed)->toBeTrue();
    expect($assertionResults[1]->reason)->toBe('good-ok');
});

it('measures duration in milliseconds per case', function (): void {
    $runner = new EvalRunner;
    $dataset = Dataset::make('d', [['input' => 'x']]);

    $run = $runner->run(function (): string {
        usleep(1_000);

        return 'ok';
    }, $dataset, []);

    expect($run->results[0]->durationMs)->toBeGreaterThan(0.0);
});

it('measures overall run duration', function (): void {
    $runner = new EvalRunner;
    $dataset = Dataset::make('d', [['input' => 'x']]);

    $run = $runner->run(function (): string {
        usleep(1_000);

        return 'ok';
    }, $dataset, []);

    expect($run->durationMs)->toBeGreaterThan(0.0);
});

it('rejects non-Assertion items in the assertions array', function (): void {
    $runner = new EvalRunner;
    $dataset = Dataset::make('d', [['input' => 'x']]);

    $runner->run(fn (): string => 'x', $dataset, ['not-an-assertion']);
})->throws(InvalidArgumentException::class);

it('returns an empty run for an empty dataset', function (): void {
    $runner = new EvalRunner;
    $dataset = Dataset::make('d', []);

    $run = $runner->run(fn (): string => 'x', $dataset, []);

    expect($run->total())->toBe(0);
    expect($run->passed())->toBeTrue();
});

it('respects the assertion order in the results', function (): void {
    $runner = new EvalRunner;
    $first = fakeAssertion('first', fn (): AssertionResult => AssertionResult::pass('first'));
    $second = fakeAssertion('second', fn (): AssertionResult => AssertionResult::pass('second'));
    $dataset = Dataset::make('d', [['input' => 'x']]);

    $run = $runner->run(fn (): string => 'x', $dataset, [$first, $second]);

    $reasons = array_map(fn (AssertionResult $r): string => $r->reason, $run->results[0]->assertionResults);
    expect($reasons)->toBe(['first', 'second']);
});

it('exposes the dataset on the resulting EvalRun', function (): void {
    $runner = new EvalRunner;
    $dataset = Dataset::make('d', [['input' => 'x']]);

    $run = $runner->run(fn (): string => 'x', $dataset, [ContainsAssertion::make('x')]);

    expect($run->dataset)->toBe($dataset);
});
