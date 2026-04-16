<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Pricing\PricingTable;

it('builds a table from an array of model prices', function (): void {
    $table = PricingTable::fromArray([
        'model-a' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]);

    expect($table)->toBeInstanceOf(PricingTable::class);
    expect($table->all())->toBe([
        'model-a' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]);
});

it('reports whether a model is in the table', function (): void {
    $table = PricingTable::fromArray([
        'model-a' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]);

    expect($table->has('model-a'))->toBeTrue();
    expect($table->has('model-b'))->toBeFalse();
});

it('computes cost for a known model', function (): void {
    $table = PricingTable::fromArray([
        'model-a' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]);

    $cost = $table->cost('model-a', 1000, 500);

    expect($cost)->toBe(0.0105);
});

it('returns null cost for an unknown model', function (): void {
    $table = PricingTable::fromArray([
        'model-a' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]);

    expect($table->cost('missing', 1000, 500))->toBeNull();
});

it('rounds cost to 6 decimals', function (): void {
    $table = PricingTable::fromArray([
        'model-a' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]);

    $cost = $table->cost('model-a', 1, 1);

    // (1/1e6)*3 + (1/1e6)*15 = 0.000003 + 0.000015 = 0.000018
    expect($cost)->toBe(0.000018);
});

it('handles zero tokens correctly', function (): void {
    $table = PricingTable::fromArray([
        'model-a' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]);

    expect($table->cost('model-a', 0, 0))->toBe(0.0);
});

it('rejects negative input tokens', function (): void {
    $table = PricingTable::fromArray([
        'model-a' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]);

    $table->cost('model-a', -1, 0);
})->throws(InvalidArgumentException::class);

it('rejects negative output tokens', function (): void {
    $table = PricingTable::fromArray([
        'model-a' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]);

    $table->cost('model-a', 0, -1);
})->throws(InvalidArgumentException::class);

it('rejects entries missing input_per_1m', function (): void {
    PricingTable::fromArray([
        'broken' => ['output_per_1m' => 15.0],
    ]);
})->throws(InvalidArgumentException::class, 'broken');

it('rejects entries missing output_per_1m', function (): void {
    PricingTable::fromArray([
        'broken' => ['input_per_1m' => 3.0],
    ]);
})->throws(InvalidArgumentException::class, 'broken');

it('rejects entries with negative input_per_1m', function (): void {
    PricingTable::fromArray([
        'broken' => ['input_per_1m' => -1.0, 'output_per_1m' => 0.0],
    ]);
})->throws(InvalidArgumentException::class, 'broken');

it('rejects entries with negative output_per_1m', function (): void {
    PricingTable::fromArray([
        'broken' => ['input_per_1m' => 0.0, 'output_per_1m' => -0.5],
    ]);
})->throws(InvalidArgumentException::class, 'broken');

it('rejects entries with non-numeric input_per_1m', function (): void {
    PricingTable::fromArray([
        'broken' => ['input_per_1m' => 'cheap', 'output_per_1m' => 0.0],
    ]);
})->throws(InvalidArgumentException::class, 'broken');

it('exposes the full table via all()', function (): void {
    $data = [
        'model-a' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
        'model-b' => ['input_per_1m' => 0.5, 'output_per_1m' => 2.0],
    ];
    $table = PricingTable::fromArray($data);

    expect($table->all())->toBe($data);
});

it('handles embedding-style models with zero output price', function (): void {
    $table = PricingTable::fromArray([
        'embed-model' => ['input_per_1m' => 0.02, 'output_per_1m' => 0.0],
    ]);

    $cost = $table->cost('embed-model', 1_000_000, 5_000);

    // (1_000_000/1e6)*0.02 + (5_000/1e6)*0.0 = 0.02
    expect($cost)->toBe(0.02);
});

it('accepts integer prices coerced to float', function (): void {
    $table = PricingTable::fromArray([
        'model-a' => ['input_per_1m' => 3, 'output_per_1m' => 15],
    ]);

    expect($table->cost('model-a', 1_000_000, 1_000_000))->toBe(18.0);
});
