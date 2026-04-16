<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Pricing\PricingTable;

it('builds a table from an array of model prices', function (): void {
    $table = PricingTable::fromArray([
        'model-a' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]);

    expect($table)->toBeInstanceOf(PricingTable::class);
    expect($table->all())->toBe([
        'model-a' => [
            'input_per_1m' => 3.0,
            'output_per_1m' => 15.0,
            'cache_read_per_1m' => null,
            'cache_write_per_1m' => null,
            'reasoning_per_1m' => null,
        ],
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

    expect($table->all())->toBe([
        'model-a' => [
            'input_per_1m' => 3.0,
            'output_per_1m' => 15.0,
            'cache_read_per_1m' => null,
            'cache_write_per_1m' => null,
            'reasoning_per_1m' => null,
        ],
        'model-b' => [
            'input_per_1m' => 0.5,
            'output_per_1m' => 2.0,
            'cache_read_per_1m' => null,
            'cache_write_per_1m' => null,
            'reasoning_per_1m' => null,
        ],
    ]);
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

it('accepts entries with cache and reasoning rates', function (): void {
    $table = PricingTable::fromArray([
        'cached-model' => [
            'input_per_1m' => 3.0,
            'output_per_1m' => 15.0,
            'cache_read_per_1m' => 0.3,
            'cache_write_per_1m' => 3.75,
            'reasoning_per_1m' => 15.0,
        ],
    ]);

    expect($table)->toBeInstanceOf(PricingTable::class);
    expect($table->has('cached-model'))->toBeTrue();
});

it('computes cost with cache read and write tokens', function (): void {
    $table = PricingTable::fromArray([
        'cached-model' => [
            'input_per_1m' => 3.0,
            'output_per_1m' => 15.0,
            'cache_read_per_1m' => 0.3,
            'cache_write_per_1m' => 3.75,
        ],
    ]);

    $cost = $table->cost(
        'cached-model',
        tokensIn: 1_000,
        tokensOut: 500,
        cacheReadTokens: 2_000,
        cacheWriteTokens: 4_000,
    );

    // input: 1000/1e6 * 3.0 = 0.003
    // output: 500/1e6 * 15.0 = 0.0075
    // cache_read: 2000/1e6 * 0.3 = 0.0006
    // cache_write: 4000/1e6 * 3.75 = 0.015
    // total: 0.0261
    expect($cost)->toBe(0.0261);
});

it('falls back to input rate for cache reads when not defined', function (): void {
    $table = PricingTable::fromArray([
        'plain-model' => [
            'input_per_1m' => 3.0,
            'output_per_1m' => 15.0,
        ],
    ]);

    $cost = $table->cost(
        'plain-model',
        tokensIn: 1_000,
        tokensOut: 500,
        cacheReadTokens: 500,
    );

    // Cache reads fall back to input rate.
    // input: 1000/1e6 * 3.0 = 0.003
    // cache_read (fallback input): 500/1e6 * 3.0 = 0.0015
    // output: 500/1e6 * 15.0 = 0.0075
    // total: 0.012
    expect($cost)->toBe(0.012);
});

it('falls back to input rate for cache writes when not defined', function (): void {
    $table = PricingTable::fromArray([
        'plain-model' => [
            'input_per_1m' => 3.0,
            'output_per_1m' => 15.0,
        ],
    ]);

    $cost = $table->cost(
        'plain-model',
        tokensIn: 1_000,
        tokensOut: 0,
        cacheWriteTokens: 500,
    );

    // input: 1000/1e6 * 3.0 = 0.003
    // cache_write (fallback input): 500/1e6 * 3.0 = 0.0015
    // total: 0.0045
    expect($cost)->toBe(0.0045);
});

it('falls back to output rate for reasoning when not defined', function (): void {
    $table = PricingTable::fromArray([
        'plain-model' => [
            'input_per_1m' => 3.0,
            'output_per_1m' => 15.0,
        ],
    ]);

    $cost = $table->cost(
        'plain-model',
        tokensIn: 0,
        tokensOut: 1_000,
        reasoningTokens: 500,
    );

    // output: 1000/1e6 * 15.0 = 0.015
    // reasoning (fallback output): 500/1e6 * 15.0 = 0.0075
    // total: 0.0225
    expect($cost)->toBe(0.0225);
});

it('uses reasoning rate when defined', function (): void {
    $table = PricingTable::fromArray([
        'o1-preview' => [
            'input_per_1m' => 15.0,
            'output_per_1m' => 60.0,
            'reasoning_per_1m' => 60.0,
        ],
    ]);

    $cost = $table->cost(
        'o1-preview',
        tokensIn: 1_000,
        tokensOut: 500,
        reasoningTokens: 2_000,
    );

    // input: 1000/1e6 * 15.0 = 0.015
    // output: 500/1e6 * 60.0 = 0.03
    // reasoning: 2000/1e6 * 60.0 = 0.12
    // total: 0.165
    expect($cost)->toBe(0.165);
});

it('handles zero cache and reasoning tokens', function (): void {
    $table = PricingTable::fromArray([
        'cached-model' => [
            'input_per_1m' => 3.0,
            'output_per_1m' => 15.0,
            'cache_read_per_1m' => 0.3,
            'cache_write_per_1m' => 3.75,
            'reasoning_per_1m' => 15.0,
        ],
    ]);

    $costZero = $table->cost(
        'cached-model',
        tokensIn: 1_000,
        tokensOut: 500,
        cacheReadTokens: 0,
        cacheWriteTokens: 0,
        reasoningTokens: 0,
    );

    $costPlain = $table->cost('cached-model', 1_000, 500);

    expect($costZero)->toBe($costPlain);
});

it('rejects negative cache_read_per_1m', function (): void {
    PricingTable::fromArray([
        'broken' => [
            'input_per_1m' => 1.0,
            'output_per_1m' => 2.0,
            'cache_read_per_1m' => -0.5,
        ],
    ]);
})->throws(InvalidArgumentException::class, 'broken');

it('rejects negative cache_write_per_1m', function (): void {
    PricingTable::fromArray([
        'broken' => [
            'input_per_1m' => 1.0,
            'output_per_1m' => 2.0,
            'cache_write_per_1m' => -1.0,
        ],
    ]);
})->throws(InvalidArgumentException::class, 'broken');

it('rejects negative reasoning_per_1m', function (): void {
    PricingTable::fromArray([
        'broken' => [
            'input_per_1m' => 1.0,
            'output_per_1m' => 2.0,
            'reasoning_per_1m' => -1.0,
        ],
    ]);
})->throws(InvalidArgumentException::class, 'broken');

it('rejects negative cache read tokens in cost()', function (): void {
    $table = PricingTable::fromArray([
        'model-a' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]);

    $table->cost('model-a', 10, 10, cacheReadTokens: -1);
})->throws(InvalidArgumentException::class);

it('rejects negative cache write tokens in cost()', function (): void {
    $table = PricingTable::fromArray([
        'model-a' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]);

    $table->cost('model-a', 10, 10, cacheWriteTokens: -1);
})->throws(InvalidArgumentException::class);

it('rejects negative reasoning tokens in cost()', function (): void {
    $table = PricingTable::fromArray([
        'model-a' => ['input_per_1m' => 3.0, 'output_per_1m' => 15.0],
    ]);

    $table->cost('model-a', 10, 10, reasoningTokens: -1);
})->throws(InvalidArgumentException::class);

it('preserves backwards compatibility with entries missing optional fields', function (): void {
    $table = PricingTable::fromArray([
        'legacy-model' => ['input_per_1m' => 1.0, 'output_per_1m' => 2.0],
    ]);

    expect($table->all())->toBe([
        'legacy-model' => [
            'input_per_1m' => 1.0,
            'output_per_1m' => 2.0,
            'cache_read_per_1m' => null,
            'cache_write_per_1m' => null,
            'reasoning_per_1m' => null,
        ],
    ]);
});
