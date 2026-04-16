<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Support\Dataset;

it('creates a dataset with a name and cases', function (): void {
    $dataset = Dataset::make('my-dataset', [
        ['input' => 'hello'],
        ['input' => 'world', 'expected' => 'world'],
    ]);

    expect($dataset->name)->toBe('my-dataset');
    expect($dataset->cases)->toHaveCount(2);
    expect($dataset->cases[0])->toBe(['input' => 'hello']);
});

it('exposes the case count', function (): void {
    $dataset = Dataset::make('x', [
        ['input' => 1],
        ['input' => 2],
        ['input' => 3],
    ]);

    expect($dataset->count())->toBe(3);
});

it('supports an empty dataset', function (): void {
    $dataset = Dataset::make('empty', []);

    expect($dataset->count())->toBe(0);
    expect($dataset->isEmpty())->toBeTrue();
});

it('rejects an empty name', function (): void {
    Dataset::make('', [['input' => 'x']]);
})->throws(InvalidArgumentException::class);

it('rejects a name that is only whitespace', function (): void {
    Dataset::make("   \t\n", [['input' => 'x']]);
})->throws(InvalidArgumentException::class);

it('rejects cases that are not arrays', function (): void {
    Dataset::make('bad', ['not-an-array']);
})->throws(InvalidArgumentException::class);

it('rejects cases missing the input key', function (): void {
    Dataset::make('bad', [['expected' => 'y']]);
})->throws(InvalidArgumentException::class);

it('reports the index of an invalid case in the exception message', function (): void {
    $caught = null;
    try {
        Dataset::make('bad', [
            ['input' => 'ok'],
            ['expected' => 'missing-input'],
        ]);
    } catch (InvalidArgumentException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof InvalidArgumentException) {
        throw new RuntimeException('Expected InvalidArgumentException was not thrown.');
    }

    expect($caught->getMessage())->toContain('1');
});

it('accepts cases with only an input', function (): void {
    $dataset = Dataset::make('x', [['input' => null]]);

    expect($dataset->count())->toBe(1);
    expect($dataset->cases[0])->toBe(['input' => null]);
});

it('accepts cases with input, expected, and meta', function (): void {
    $dataset = Dataset::make('x', [
        ['input' => 'a', 'expected' => 'A', 'meta' => ['tag' => 'smoke']],
    ]);

    expect($dataset->cases[0]['meta'])->toBe(['tag' => 'smoke']);
});

it('rejects non-array meta', function (): void {
    Dataset::make('bad', [
        ['input' => 'a', 'meta' => 'not-an-array'],
    ]);
})->throws(InvalidArgumentException::class);

it('is immutable', function (): void {
    $dataset = Dataset::make('immutable', [['input' => 'x']]);

    expect(fn () => (function () use ($dataset): void {
        /** @phpstan-ignore-next-line */
        $dataset->name = 'changed';
    })())->toThrow(Error::class);
});
