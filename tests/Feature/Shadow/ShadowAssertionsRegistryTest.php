<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Assertions\CostLimit;
use Mosaiqo\Proofread\Proofread;
use Mosaiqo\Proofread\Shadow\ShadowAssertionsNotRegisteredException;
use Mosaiqo\Proofread\Shadow\ShadowAssertionsRegistry;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\EchoAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\ShadowedEchoAgent;

it('registers assertions for an agent', function (): void {
    $registry = new ShadowAssertionsRegistry;

    $registry->register(ShadowedEchoAgent::class, fn (): array => [
        ContainsAssertion::make('hello'),
    ]);

    $assertions = $registry->forAgent(ShadowedEchoAgent::class);

    expect($assertions)->toHaveCount(1)
        ->and($assertions[0])->toBeInstanceOf(ContainsAssertion::class);
});

it('resolves registered assertions lazily', function (): void {
    $registry = new ShadowAssertionsRegistry;
    $called = 0;

    $registry->register(ShadowedEchoAgent::class, function () use (&$called): array {
        $called++;

        return [ContainsAssertion::make('x')];
    });

    expect($called)->toBe(0);

    $registry->forAgent(ShadowedEchoAgent::class);

    expect($called)->toBe(1);
});

it('returns true for hasAssertionsFor when registered', function (): void {
    $registry = new ShadowAssertionsRegistry;

    $registry->register(ShadowedEchoAgent::class, fn (): array => []);

    expect($registry->hasAssertionsFor(ShadowedEchoAgent::class))->toBeTrue();
});

it('returns false for hasAssertionsFor when not registered', function (): void {
    $registry = new ShadowAssertionsRegistry;

    expect($registry->hasAssertionsFor(ShadowedEchoAgent::class))->toBeFalse();
});

it('throws ShadowAssertionsNotRegisteredException for unregistered agents', function (): void {
    $registry = new ShadowAssertionsRegistry;

    $registry->forAgent(ShadowedEchoAgent::class);
})->throws(
    ShadowAssertionsNotRegisteredException::class,
    'No shadow assertions registered for agent class ['.ShadowedEchoAgent::class.'].'
);

it('rejects empty agent class', function (): void {
    $registry = new ShadowAssertionsRegistry;

    $registry->register('', fn (): array => []);
})->throws(InvalidArgumentException::class, 'Agent class cannot be empty.');

it('rejects non-array resolver returns', function (): void {
    $registry = new ShadowAssertionsRegistry;

    $registry->register(ShadowedEchoAgent::class, fn (): string => 'not an array');

    $registry->forAgent(ShadowedEchoAgent::class);
})->throws(
    InvalidArgumentException::class,
    'Shadow assertions resolver for ['.ShadowedEchoAgent::class.'] must return an array.'
);

it('rejects non-Assertion items in resolver return', function (): void {
    $registry = new ShadowAssertionsRegistry;

    $registry->register(ShadowedEchoAgent::class, fn (): array => [
        ContainsAssertion::make('ok'),
        'not an assertion',
    ]);

    $registry->forAgent(ShadowedEchoAgent::class);
})->throws(
    InvalidArgumentException::class,
    'Shadow assertions resolver for ['.ShadowedEchoAgent::class.'] returned a non-Assertion at index 1.'
);

it('lists registered agents', function (): void {
    $registry = new ShadowAssertionsRegistry;

    expect($registry->registeredAgents())->toBe([]);

    $registry->register(ShadowedEchoAgent::class, fn (): array => []);
    $registry->register(EchoAgent::class, fn (): array => []);

    expect($registry->registeredAgents())->toBe([
        ShadowedEchoAgent::class,
        EchoAgent::class,
    ]);
});

it('overwrites an existing registration', function (): void {
    $registry = new ShadowAssertionsRegistry;

    $registry->register(ShadowedEchoAgent::class, fn (): array => [
        ContainsAssertion::make('first'),
    ]);
    $registry->register(ShadowedEchoAgent::class, fn (): array => [
        CostLimit::under(0.01),
    ]);

    $assertions = $registry->forAgent(ShadowedEchoAgent::class);

    expect($assertions)->toHaveCount(1)
        ->and($assertions[0])->toBeInstanceOf(CostLimit::class);
});

it('is accessible via Proofread::registerShadowAssertions()', function (): void {
    $registry = app(ShadowAssertionsRegistry::class);

    Proofread::registerShadowAssertions(ShadowedEchoAgent::class, fn (): array => [
        ContainsAssertion::make('global'),
    ]);

    expect($registry->hasAssertionsFor(ShadowedEchoAgent::class))->toBeTrue();

    $assertions = $registry->forAgent(ShadowedEchoAgent::class);

    expect($assertions)->toHaveCount(1)
        ->and($assertions[0])->toBeInstanceOf(ContainsAssertion::class);
});

it('allows registering multiple agents', function (): void {
    $registry = new ShadowAssertionsRegistry;

    $registry->register(ShadowedEchoAgent::class, fn (): array => [
        ContainsAssertion::make('shadowed'),
    ]);
    $registry->register(EchoAgent::class, fn (): array => [
        CostLimit::under(0.05),
    ]);

    expect($registry->forAgent(ShadowedEchoAgent::class)[0])->toBeInstanceOf(ContainsAssertion::class)
        ->and($registry->forAgent(EchoAgent::class)[0])->toBeInstanceOf(CostLimit::class);
});
