<?php

declare(strict_types=1);

use Laravel\Ai\Contracts\Agent;
use Mosaiqo\Proofread\Runner\SubjectResolver;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\EchoAgent;

it('returns the same callable when given a callable', function (): void {
    $resolver = new SubjectResolver;
    $callable = fn (string $input): string => strtoupper($input);

    $resolved = $resolver->resolve($callable);

    expect($resolved('hi', ['input' => 'hi']))->toBe('HI');
});

it('resolves an Agent FQCN through the container', function (): void {
    EchoAgent::fake(['positive']);
    $resolver = new SubjectResolver;

    $resolved = $resolver->resolve(EchoAgent::class);

    expect($resolved('I love it', ['input' => 'I love it']))->toBe('positive');
});

it('resolves an Agent instance directly', function (): void {
    EchoAgent::fake(['neutral']);
    $resolver = new SubjectResolver;

    $agent = new EchoAgent;
    $resolved = $resolver->resolve($agent);

    expect($resolved('something', ['input' => 'something']))->toBe('neutral');
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

    expect($resolved('x', ['input' => 'x']))->toBe('the answer');
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

    expect($resolved('anything', ['input' => 'anything']))->toBe('late-binding');
});

it('requires the agent instance to expose text responses via Promptable', function (): void {
    EchoAgent::fake(['extracted']);
    $resolver = new SubjectResolver;

    $resolved = $resolver->resolve(new EchoAgent);
    $result = $resolved('q', ['input' => 'q']);

    expect($result)->toBeString()->toBe('extracted');
});
