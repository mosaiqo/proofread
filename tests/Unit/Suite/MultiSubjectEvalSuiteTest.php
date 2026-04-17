<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Suite\MultiSubjectEvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class ThreeProviderStubSuite extends MultiSubjectEvalSuite
{
    public function name(): string
    {
        return 'three-provider-stub';
    }

    public function dataset(): Dataset
    {
        return Dataset::make('x', [['input' => 'a']]);
    }

    public function assertions(): array
    {
        return [];
    }

    public function subjects(): array
    {
        return [
            'haiku' => static fn (): string => 'haiku-output',
            'sonnet' => static fn (): string => 'sonnet-output',
            'opus' => static fn (): string => 'opus-output',
        ];
    }
}

final class EmptySubjectsSuite extends MultiSubjectEvalSuite
{
    public function name(): string
    {
        return 'empty';
    }

    public function dataset(): Dataset
    {
        return Dataset::make('x', []);
    }

    public function assertions(): array
    {
        return [];
    }

    public function subjects(): array
    {
        return [];
    }
}

it('extends EvalSuite', function (): void {
    $suite = new ThreeProviderStubSuite;

    expect($suite)->toBeInstanceOf(EvalSuite::class);
});

it('returns the labels-to-subjects map from subjects()', function (): void {
    $suite = new ThreeProviderStubSuite;

    $subjects = $suite->subjects();

    expect(array_keys($subjects))->toBe(['haiku', 'sonnet', 'opus']);
    foreach ($subjects as $subject) {
        expect($subject)->toBeCallable();
    }
});

it('returns the first subject from subject() for backward compatibility', function (): void {
    $suite = new ThreeProviderStubSuite;

    $subject = $suite->subject();

    expect($subject)->toBeCallable();

    /** @var callable $subject */
    expect($subject())->toBe('haiku-output');
});

it('throws when subjects() is empty', function (): void {
    $suite = new EmptySubjectsSuite;

    expect(fn () => $suite->subject())
        ->toThrow(LogicException::class, 'EmptySubjectsSuite');
});

it('makes subject() final', function (): void {
    $reflection = new ReflectionClass(MultiSubjectEvalSuite::class);
    $method = $reflection->getMethod('subject');

    expect($method->isFinal())->toBeTrue();
});

it('inherits setUp, tearDown, and assertionsFor from EvalSuite', function (): void {
    $suite = new ThreeProviderStubSuite;

    $suite->setUp();
    $suite->tearDown();
    $result = $suite->assertionsFor(['input' => 'a']);

    expect($result)->toBeArray();
});
