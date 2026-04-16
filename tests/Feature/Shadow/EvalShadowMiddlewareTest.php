<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Bus;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Mosaiqo\Proofread\Shadow\Contracts\RandomNumberProvider;
use Mosaiqo\Proofread\Shadow\Jobs\PersistShadowCaptureJob;
use Mosaiqo\Proofread\Shadow\MtRandRandomNumberProvider;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\ShadowedEchoAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Shadow\SequenceRandomNumberProvider;

function configureShadow(float $sampleRate = 1.0, bool $enabled = true, string $queue = 'default'): void
{
    /** @var Repository $config */
    $config = app('config');
    $config->set('proofread.shadow.enabled', $enabled);
    $config->set('proofread.shadow.sample_rate', $sampleRate);
    $config->set('proofread.shadow.queue', $queue);
    $config->set('proofread.shadow.agents', []);
}

function fakeShadowedAgent(string $text = 'echo', string $model = 'claude-haiku-4-5'): void
{
    ShadowedEchoAgent::fake(function ($prompt, $attachments, $provider) use ($text, $model): AgentResponse {
        return new AgentResponse(
            invocationId: 'inv-1',
            text: $text,
            usage: new Usage(promptTokens: 100, completionTokens: 50),
            meta: new Meta($provider->name(), $model),
        );
    });
}

it('does not dispatch when shadow is disabled', function (): void {
    configureShadow(sampleRate: 1.0, enabled: false);
    Bus::fake();
    fakeShadowedAgent();

    ShadowedEchoAgent::make()->prompt('hello');

    Bus::assertNotDispatched(PersistShadowCaptureJob::class);
});

it('always dispatches when sample rate is 1.0', function (): void {
    configureShadow(sampleRate: 1.0);
    Bus::fake();
    fakeShadowedAgent();

    for ($i = 0; $i < 5; $i++) {
        ShadowedEchoAgent::make()->prompt('hello');
    }

    Bus::assertDispatchedTimes(PersistShadowCaptureJob::class, 5);
});

it('never dispatches when sample rate is 0.0', function (): void {
    configureShadow(sampleRate: 0.0);
    Bus::fake();
    fakeShadowedAgent();

    for ($i = 0; $i < 5; $i++) {
        ShadowedEchoAgent::make()->prompt('hello');
    }

    Bus::assertNotDispatched(PersistShadowCaptureJob::class);
});

it('uses per-agent sample rate when configured', function (): void {
    /** @var Repository $config */
    $config = app('config');
    $config->set('proofread.shadow.enabled', true);
    $config->set('proofread.shadow.sample_rate', 0.0);
    $config->set('proofread.shadow.queue', 'default');
    $config->set('proofread.shadow.agents', [
        ShadowedEchoAgent::class => ['sample_rate' => 1.0],
    ]);

    Bus::fake();
    fakeShadowedAgent();

    ShadowedEchoAgent::make()->prompt('hello');

    Bus::assertDispatchedTimes(PersistShadowCaptureJob::class, 1);
});

it('does not block or affect the agent response', function (): void {
    configureShadow(sampleRate: 1.0);
    Bus::fake();
    fakeShadowedAgent('the-expected-text');

    $response = ShadowedEchoAgent::make()->prompt('hello');

    expect($response->text)->toBe('the-expected-text');
});

it('dispatches to the configured queue', function (): void {
    configureShadow(sampleRate: 1.0, queue: 'evals-low');
    Bus::fake();
    fakeShadowedAgent();

    ShadowedEchoAgent::make()->prompt('hello');

    Bus::assertDispatched(PersistShadowCaptureJob::class, function (PersistShadowCaptureJob $job): bool {
        return $job->queue === 'evals-low';
    });
});

it('captures the input output tokens model and latency in the payload', function (): void {
    configureShadow(sampleRate: 1.0);
    Bus::fake();
    fakeShadowedAgent('the-output', 'claude-haiku-4-5');

    ShadowedEchoAgent::make()->prompt('the-input');

    Bus::assertDispatched(PersistShadowCaptureJob::class, function (PersistShadowCaptureJob $job): bool {
        expect($job->agentClass)->toBe(ShadowedEchoAgent::class)
            ->and($job->payload['raw_input']['prompt'])->toBe('the-input')
            ->and($job->payload['output'])->toBe('the-output')
            ->and($job->payload['tokens_in'])->toBe(100)
            ->and($job->payload['tokens_out'])->toBe(50)
            ->and($job->payload['model'])->toBe('claude-haiku-4-5')
            ->and($job->payload['latency_ms'])->toBeFloat()
            ->and($job->payload['latency_ms'])->toBeGreaterThanOrEqual(0.0)
            ->and($job->payload['captured_at'])->toBeInstanceOf(DateTimeInterface::class);

        return true;
    });
});

it('records the effective sample rate on the capture', function (): void {
    /** @var Repository $config */
    $config = app('config');
    $config->set('proofread.shadow.enabled', true);
    $config->set('proofread.shadow.sample_rate', 0.25);
    $config->set('proofread.shadow.queue', 'default');
    $config->set('proofread.shadow.agents', [
        ShadowedEchoAgent::class => ['sample_rate' => 0.75],
    ]);

    app()->instance(RandomNumberProvider::class, new SequenceRandomNumberProvider(0.0));

    Bus::fake();
    fakeShadowedAgent();

    ShadowedEchoAgent::make()->prompt('hello');

    Bus::assertDispatched(PersistShadowCaptureJob::class, function (PersistShadowCaptureJob $job): bool {
        return $job->sampleRate === 0.75;
    });
});

it('never samples when the provider returns a value >= the sample rate', function (): void {
    configureShadow(sampleRate: 0.4);
    app()->instance(RandomNumberProvider::class, new SequenceRandomNumberProvider(0.5));

    Bus::fake();
    fakeShadowedAgent();

    ShadowedEchoAgent::make()->prompt('hello');

    Bus::assertNotDispatched(PersistShadowCaptureJob::class);
});

it('samples when the provider returns a value < the sample rate', function (): void {
    configureShadow(sampleRate: 0.5);
    app()->instance(RandomNumberProvider::class, new SequenceRandomNumberProvider(0.1));

    Bus::fake();
    fakeShadowedAgent();

    ShadowedEchoAgent::make()->prompt('hello');

    Bus::assertDispatchedTimes(PersistShadowCaptureJob::class, 1);
});

it('uses the MtRand provider by default', function (): void {
    expect(app(RandomNumberProvider::class))->toBeInstanceOf(MtRandRandomNumberProvider::class);
});
