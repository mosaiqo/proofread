<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\EchoAgent;

it('accepts an Agent FQCN as subject', function (): void {
    EchoAgent::fake(['positive', 'positive']);
    $runner = new EvalRunner;
    $dataset = Dataset::make('agent-fqcn', [
        ['input' => 'I love this'],
        ['input' => 'this is great'],
    ]);

    $run = $runner->run(EchoAgent::class, $dataset, [ContainsAssertion::make('positive')]);

    expect($run->total())->toBe(2);
    expect($run->passed())->toBeTrue();
});

it('accepts an Agent instance as subject', function (): void {
    EchoAgent::fake(['neutral']);
    $runner = new EvalRunner;
    $dataset = Dataset::make('agent-instance', [['input' => 'meh']]);

    $run = $runner->run(new EchoAgent, $dataset, [ContainsAssertion::make('neutral')]);

    expect($run->passed())->toBeTrue();
});

it('captures Agent exceptions as case errors', function (): void {
    EchoAgent::fake(function (string $prompt): never {
        throw new RuntimeException('agent-boom: '.$prompt);
    });
    $runner = new EvalRunner;
    $dataset = Dataset::make('agent-boom', [['input' => 'x']]);

    $run = $runner->run(EchoAgent::class, $dataset, []);

    expect($run->results[0]->hasError())->toBeTrue();
    expect($run->results[0]->error?->getMessage())->toContain('agent-boom: x');
});

it('still accepts callables as subjects', function (): void {
    $runner = new EvalRunner;
    $dataset = Dataset::make('cb', [['input' => 'alpha']]);

    $run = $runner->run(
        fn (string $input): string => strtoupper($input),
        $dataset,
        [ContainsAssertion::make('ALPHA')]
    );

    expect($run->passed())->toBeTrue();
});

it('rejects unsupported subject types upfront', function (): void {
    $runner = new EvalRunner;
    $dataset = Dataset::make('bad', [['input' => 'x']]);

    $runner->run(42, $dataset, []);
})->throws(InvalidArgumentException::class);

it('rejects a non-Agent class FQCN', function (): void {
    $runner = new EvalRunner;
    $dataset = Dataset::make('bad-class', [['input' => 'x']]);

    $runner->run(stdClass::class, $dataset, []);
})->throws(InvalidArgumentException::class);
