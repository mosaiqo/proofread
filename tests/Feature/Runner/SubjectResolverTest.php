<?php

declare(strict_types=1);

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Mosaiqo\Proofread\Pricing\PricingTable;
use Mosaiqo\Proofread\Runner\SubjectInvocation;
use Mosaiqo\Proofread\Runner\SubjectResolver;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\EchoAgent;

it('returns the same callable when given a callable', function (): void {
    $resolver = new SubjectResolver;
    $callable = fn (string $input): string => strtoupper($input);

    $resolved = $resolver->resolve($callable);

    expect($resolved('hi', ['input' => 'hi'])->output)->toBe('HI');
});

it('resolves an Agent FQCN through the container', function (): void {
    EchoAgent::fake(['positive']);
    $resolver = new SubjectResolver;

    $resolved = $resolver->resolve(EchoAgent::class);

    expect($resolved('I love it', ['input' => 'I love it'])->output)->toBe('positive');
});

it('resolves an Agent instance directly', function (): void {
    EchoAgent::fake(['neutral']);
    $resolver = new SubjectResolver;

    $agent = new EchoAgent;
    $resolved = $resolver->resolve($agent);

    expect($resolved('something', ['input' => 'something'])->output)->toBe('neutral');
});

it('invokes the agent with the case input', function (): void {
    $seen = [];
    EchoAgent::fake(function (string $prompt) use (&$seen): string {
        $seen[] = $prompt;

        return 'ok';
    });
    $resolver = new SubjectResolver;

    $resolved = $resolver->resolve(EchoAgent::class);
    $resolved('hello world', ['input' => 'hello world']);

    expect($seen)->toBe(['hello world']);
});

it('extracts the text from the agent response', function (): void {
    EchoAgent::fake(['the answer']);
    $resolver = new SubjectResolver;

    $resolved = $resolver->resolve(EchoAgent::class);

    expect($resolved('x', ['input' => 'x'])->output)->toBe('the answer');
});

it('rejects a string that is not a class', function (): void {
    $resolver = new SubjectResolver;

    $resolver->resolve('not-a-class-at-all');
})->throws(InvalidArgumentException::class, 'not-a-class-at-all');

it('rejects a class that does not extend Agent', function (): void {
    $resolver = new SubjectResolver;

    $resolver->resolve(stdClass::class);
})->throws(InvalidArgumentException::class, Agent::class);

it('rejects integer subjects', function (): void {
    $resolver = new SubjectResolver;

    $resolver->resolve(42);
})->throws(InvalidArgumentException::class);

it('rejects array subjects', function (): void {
    $resolver = new SubjectResolver;

    $resolver->resolve(['foo' => 'bar']);
})->throws(InvalidArgumentException::class);

it('rejects null subjects', function (): void {
    $resolver = new SubjectResolver;

    $resolver->resolve(null);
})->throws(InvalidArgumentException::class);

it('resolves lazily so the container binding is looked up at call time', function (): void {
    $resolver = new SubjectResolver;
    $resolved = $resolver->resolve(EchoAgent::class);

    EchoAgent::fake(['late-binding']);

    expect($resolved('anything', ['input' => 'anything'])->output)->toBe('late-binding');
});

it('requires the agent instance to expose text responses via Promptable', function (): void {
    EchoAgent::fake(['extracted']);
    $resolver = new SubjectResolver;

    $resolved = $resolver->resolve(new EchoAgent);
    $invocation = $resolved('q', ['input' => 'q']);

    expect($invocation->output)->toBeString()->toBe('extracted');
});

it('returns a SubjectInvocation from callable subjects with empty metadata', function (): void {
    $resolver = new SubjectResolver;
    $resolved = $resolver->resolve(fn (string $input): string => strtoupper($input));

    $invocation = $resolved('hi', ['input' => 'hi']);

    expect($invocation)->toBeInstanceOf(SubjectInvocation::class);
    expect($invocation->output)->toBe('HI');
    expect($invocation->metadata)->toBe([]);
});

it('returns a SubjectInvocation from Agent subjects with populated metadata', function (): void {
    EchoAgent::fake(['sentiment']);
    $resolver = new SubjectResolver;

    $resolved = $resolver->resolve(EchoAgent::class);
    $invocation = $resolved('hello', ['input' => 'hello']);

    expect($invocation)->toBeInstanceOf(SubjectInvocation::class);
    expect($invocation->output)->toBe('sentiment');
    expect($invocation->metadata)->toHaveKeys([
        'tokens_in',
        'tokens_out',
        'tokens_total',
        'cost_usd',
        'model',
        'provider',
        'raw',
    ]);
});

it('leaves metadata keys as null when the SDK does not provide them', function (): void {
    EchoAgent::fake(['x']);
    $resolver = new SubjectResolver;

    $resolved = $resolver->resolve(EchoAgent::class);
    $invocation = $resolved('x', ['input' => 'x']);

    // The fake gateway populates Usage with zeros, so tokens_* are 0, not null.
    // But cost is never computed, so it must be null.
    expect($invocation->metadata['cost_usd'])->toBeNull();
    // With the fake's zero Usage:
    expect($invocation->metadata['tokens_in'])->toBe(0);
    expect($invocation->metadata['tokens_out'])->toBe(0);
    expect($invocation->metadata['tokens_total'])->toBe(0);
    // Meta carries provider and model from the fake gateway.
    expect($invocation->metadata['provider'])->toBeString();
    expect($invocation->metadata['model'])->toBeString();
});

it('exposes the AgentResponse under the raw metadata key', function (): void {
    EchoAgent::fake(['x']);
    $resolver = new SubjectResolver;

    $resolved = $resolver->resolve(EchoAgent::class);
    $invocation = $resolved('x', ['input' => 'x']);

    expect($invocation->metadata['raw'])->toBeInstanceOf(AgentResponse::class);
});

it('populates cost_usd when the model is in the pricing table', function (): void {
    $resolver = new SubjectResolver(PricingTable::fromArray([
        'priced-model' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]));
    EchoAgent::fake(function ($prompt, $attachments, $provider) {
        return new AgentResponse(
            invocationId: 'inv-1',
            text: 'hello',
            usage: new Usage(promptTokens: 1_000, completionTokens: 500),
            meta: new Meta($provider->name(), 'priced-model'),
        );
    });

    $resolved = $resolver->resolve(EchoAgent::class);
    $invocation = $resolved('x', ['input' => 'x']);

    expect($invocation->metadata['cost_usd'])->toBe(0.0105);
    expect($invocation->metadata['tokens_in'])->toBe(1_000);
    expect($invocation->metadata['tokens_out'])->toBe(500);
});

it('leaves cost_usd null when the model is not in the pricing table', function (): void {
    $resolver = new SubjectResolver(PricingTable::fromArray([
        'other-model' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]));
    EchoAgent::fake(function ($prompt, $attachments, $provider) {
        return new AgentResponse(
            invocationId: 'inv-1',
            text: 'hello',
            usage: new Usage(promptTokens: 100, completionTokens: 50),
            meta: new Meta($provider->name(), 'unknown-model'),
        );
    });

    $resolved = $resolver->resolve(EchoAgent::class);
    $invocation = $resolved('x', ['input' => 'x']);

    expect($invocation->metadata['cost_usd'])->toBeNull();
});

it('uses the injected pricing table when provided', function (): void {
    $custom = PricingTable::fromArray([
        'my-unique-model-xyz' => ['input_per_1m' => 10.0, 'output_per_1m' => 20.0],
    ]);
    $resolver = new SubjectResolver($custom);
    EchoAgent::fake(function ($prompt, $attachments, $provider) {
        return new AgentResponse(
            invocationId: 'inv-1',
            text: 'hi',
            usage: new Usage(promptTokens: 1_000_000, completionTokens: 500_000),
            meta: new Meta($provider->name(), 'my-unique-model-xyz'),
        );
    });

    $resolved = $resolver->resolve(EchoAgent::class);
    $invocation = $resolved('x', ['input' => 'x']);

    // (1_000_000 / 1e6) * 10 + (500_000 / 1e6) * 20 = 10 + 10 = 20.0
    expect($invocation->metadata['cost_usd'])->toBe(20.0);
});
