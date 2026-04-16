<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Examples\ExampleAgent;
use Mosaiqo\Proofread\Proofread;
use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Support\Dataset;

beforeEach(function (): void {
    Proofread::registerPestExpectations();
});

it('evaluates the example agent against the sample dataset', function (): void {
    $dataset = require __DIR__.'/../../../examples/example-dataset.php';

    expect($dataset)->toBeInstanceOf(Dataset::class);

    ExampleAgent::fake(fn (string $prompt): string => match (true) {
        str_contains($prompt, 'love') => 'positive',
        str_contains($prompt, 'terrible') => 'negative',
        default => 'neutral',
    });

    $runner = new EvalRunner;

    $run = $runner->run(ExampleAgent::class, $dataset, [
        ContainsAssertion::make('positive'),
    ]);

    expect($run->total())->toBe(3);
    expect($run->results[0]->output)->toBe('positive');
    expect($run->results[1]->output)->toBe('negative');
    expect($run->results[2]->output)->toBe('neutral');
});

it('passes the killer demo expectation for matching cases', function (): void {
    $dataset = Dataset::make('positive-only', [
        ['input' => 'I love it!', 'expected' => 'positive'],
        ['input' => 'Absolutely wonderful', 'expected' => 'positive'],
    ]);

    ExampleAgent::fake(['positive', 'positive']);

    expect(ExampleAgent::class)->toPassEval($dataset, [
        ContainsAssertion::make('positive'),
    ]);
});
