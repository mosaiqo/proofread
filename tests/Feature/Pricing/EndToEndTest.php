<?php

declare(strict_types=1);

use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Mosaiqo\Proofread\Assertions\CostLimit;
use Mosaiqo\Proofread\Pricing\PricingTable;
use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Runner\SubjectResolver;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\EchoAgent;

it('passes CostLimit end-to-end when the Agent model is priced within budget', function (): void {
    $pricing = PricingTable::fromArray([
        'cheap-model' => ['input_per_1m' => 1.0, 'output_per_1m' => 2.0],
    ]);
    EchoAgent::fake(function ($prompt, $attachments, $provider) {
        return new AgentResponse(
            invocationId: 'inv-1',
            text: 'answer',
            usage: new Usage(promptTokens: 1_000, completionTokens: 500),
            meta: new Meta($provider->name(), 'cheap-model'),
        );
    });

    $runner = new EvalRunner(new SubjectResolver($pricing));
    $dataset = Dataset::make('cost-pass', [['input' => 'hi']]);

    $run = $runner->run(EchoAgent::class, $dataset, [CostLimit::under(0.01)]);

    // (1000/1e6)*1.0 + (500/1e6)*2.0 = 0.001 + 0.001 = 0.002, under $0.01.
    expect($run->passed())->toBeTrue();
    expect($run->results[0]->assertionResults[0]->passed)->toBeTrue();
});

it('fails CostLimit end-to-end when the priced cost exceeds the budget', function (): void {
    $pricing = PricingTable::fromArray([
        'expensive-model' => ['input_per_1m' => 100.0, 'output_per_1m' => 200.0],
    ]);
    EchoAgent::fake(function ($prompt, $attachments, $provider) {
        return new AgentResponse(
            invocationId: 'inv-1',
            text: 'answer',
            usage: new Usage(promptTokens: 10_000, completionTokens: 5_000),
            meta: new Meta($provider->name(), 'expensive-model'),
        );
    });

    $runner = new EvalRunner(new SubjectResolver($pricing));
    $dataset = Dataset::make('cost-fail', [['input' => 'hi']]);

    $run = $runner->run(EchoAgent::class, $dataset, [CostLimit::under(0.001)]);

    // (10000/1e6)*100 + (5000/1e6)*200 = 1.0 + 1.0 = 2.0, well over $0.001.
    expect($run->passed())->toBeFalse();
    expect($run->results[0]->assertionResults[0]->passed)->toBeFalse();
    expect($run->results[0]->assertionResults[0]->reason)->toContain('exceeds limit');
});

it('fails CostLimit end-to-end when the Agent model is absent from the pricing table', function (): void {
    $pricing = PricingTable::fromArray([
        'some-other-model' => ['input_per_1m' => 1.0, 'output_per_1m' => 2.0],
    ]);
    EchoAgent::fake(function ($prompt, $attachments, $provider) {
        return new AgentResponse(
            invocationId: 'inv-1',
            text: 'answer',
            usage: new Usage(promptTokens: 1_000, completionTokens: 500),
            meta: new Meta($provider->name(), 'unpriced-model'),
        );
    });

    $runner = new EvalRunner(new SubjectResolver($pricing));
    $dataset = Dataset::make('cost-missing', [['input' => 'hi']]);

    $run = $runner->run(EchoAgent::class, $dataset, [CostLimit::under(0.01)]);

    expect($run->passed())->toBeFalse();
    expect($run->results[0]->assertionResults[0]->reason)->toContain('cost tracking not available');
});
