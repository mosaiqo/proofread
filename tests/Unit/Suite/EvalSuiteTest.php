<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

function proofread_make_suite_stub(): EvalSuite
{
    return new class extends EvalSuite
    {
        public function dataset(): Dataset
        {
            return Dataset::make('stub', [
                ['input' => 'hello'],
            ]);
        }

        public function subject(): mixed
        {
            return static fn (string $input): string => $input;
        }

        public function assertions(): array
        {
            return [ContainsAssertion::make('hello')];
        }
    };
}

it('marks dataset as abstract', function (): void {
    $reflection = new ReflectionClass(EvalSuite::class);
    $method = $reflection->getMethod('dataset');

    expect($method->isAbstract())->toBeTrue();
});

it('marks subject as abstract', function (): void {
    $reflection = new ReflectionClass(EvalSuite::class);
    $method = $reflection->getMethod('subject');

    expect($method->isAbstract())->toBeTrue();
});

it('marks assertions as abstract', function (): void {
    $reflection = new ReflectionClass(EvalSuite::class);
    $method = $reflection->getMethod('assertions');

    expect($method->isAbstract())->toBeTrue();
});

it('exposes the FQCN as the default name', function (): void {
    $suite = proofread_make_suite_stub();

    expect($suite->name())->toBe($suite::class);
});

it('allows subclasses to override the name', function (): void {
    $suite = new class extends EvalSuite
    {
        public function dataset(): Dataset
        {
            return Dataset::make('stub', [['input' => 'x']]);
        }

        public function subject(): mixed
        {
            return static fn (string $input): string => $input;
        }

        public function assertions(): array
        {
            return [];
        }

        public function name(): string
        {
            return 'custom-name';
        }
    };

    expect($suite->name())->toBe('custom-name');
});

it('returns a Dataset from dataset()', function (): void {
    $suite = proofread_make_suite_stub();

    expect($suite->dataset())->toBeInstanceOf(Dataset::class)
        ->and($suite->dataset()->name)->toBe('stub');
});

it('returns a subject from subject()', function (): void {
    $suite = proofread_make_suite_stub();

    $subject = $suite->subject();

    expect($subject)->toBeCallable();
});

it('returns an array of Assertion instances from assertions()', function (): void {
    $suite = proofread_make_suite_stub();

    $assertions = $suite->assertions();

    expect($assertions)->toBeArray()
        ->and($assertions)->not->toBeEmpty();

    foreach ($assertions as $assertion) {
        expect($assertion)->toBeInstanceOf(Assertion::class);
    }
});
