<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Pricing;

use InvalidArgumentException;

/**
 * Pricing table for LLM token usage.
 *
 * Each entry is normalized to include the two required rates (input and
 * output) plus three optional rates that cover provider-specific concepts:
 * Anthropic-style cache read/write tokens and OpenAI-style reasoning tokens.
 *
 * When an optional rate is not configured but the corresponding token count
 * is supplied to cost(), the table falls back to a conservative default:
 * cache reads and writes are billed at the regular input rate, and reasoning
 * tokens are billed at the regular output rate. This preserves a non-zero
 * cost signal for models whose providers charge for these tokens but whose
 * specific rates have not been entered into the table.
 */
final readonly class PricingTable
{
    /**
     * @param  array<string, array{
     *     input_per_1m: float,
     *     output_per_1m: float,
     *     cache_read_per_1m: float|null,
     *     cache_write_per_1m: float|null,
     *     reasoning_per_1m: float|null,
     * }>  $models
     */
    private function __construct(
        public array $models,
    ) {}

    /**
     * @param  array<mixed>  $models
     */
    public static function fromArray(array $models): self
    {
        $normalized = [];

        foreach ($models as $name => $entry) {
            if (! is_string($name) || $name === '') {
                throw new InvalidArgumentException(
                    'Pricing table model names must be non-empty strings.'
                );
            }

            if (! is_array($entry)) {
                throw new InvalidArgumentException(sprintf(
                    'Pricing entry for model "%s" must be an array.',
                    $name,
                ));
            }

            $input = self::requirePrice($name, $entry, 'input_per_1m');
            $output = self::requirePrice($name, $entry, 'output_per_1m');

            $normalized[$name] = [
                'input_per_1m' => $input,
                'output_per_1m' => $output,
                'cache_read_per_1m' => self::optionalPrice($name, $entry, 'cache_read_per_1m'),
                'cache_write_per_1m' => self::optionalPrice($name, $entry, 'cache_write_per_1m'),
                'reasoning_per_1m' => self::optionalPrice($name, $entry, 'reasoning_per_1m'),
            ];
        }

        return new self($normalized);
    }

    public function has(string $model): bool
    {
        return array_key_exists($model, $this->models);
    }

    public function cost(
        string $model,
        int $tokensIn,
        int $tokensOut,
        int $cacheReadTokens = 0,
        int $cacheWriteTokens = 0,
        int $reasoningTokens = 0,
    ): ?float {
        self::assertNonNegative('Input tokens', $tokensIn);
        self::assertNonNegative('Output tokens', $tokensOut);
        self::assertNonNegative('Cache read tokens', $cacheReadTokens);
        self::assertNonNegative('Cache write tokens', $cacheWriteTokens);
        self::assertNonNegative('Reasoning tokens', $reasoningTokens);

        if (! $this->has($model)) {
            return null;
        }

        $entry = $this->models[$model];

        $cacheReadRate = $entry['cache_read_per_1m'] ?? $entry['input_per_1m'];
        $cacheWriteRate = $entry['cache_write_per_1m'] ?? $entry['input_per_1m'];
        $reasoningRate = $entry['reasoning_per_1m'] ?? $entry['output_per_1m'];

        $cost = ($tokensIn / 1_000_000) * $entry['input_per_1m']
            + ($tokensOut / 1_000_000) * $entry['output_per_1m']
            + ($cacheReadTokens / 1_000_000) * $cacheReadRate
            + ($cacheWriteTokens / 1_000_000) * $cacheWriteRate
            + ($reasoningTokens / 1_000_000) * $reasoningRate;

        return round($cost, 6);
    }

    /**
     * @return array<string, array{
     *     input_per_1m: float,
     *     output_per_1m: float,
     *     cache_read_per_1m: float|null,
     *     cache_write_per_1m: float|null,
     *     reasoning_per_1m: float|null,
     * }>
     */
    public function all(): array
    {
        return $this->models;
    }

    /**
     * @param  array<mixed>  $entry
     */
    private static function requirePrice(string $model, array $entry, string $key): float
    {
        if (! array_key_exists($key, $entry)) {
            throw new InvalidArgumentException(sprintf(
                'Pricing entry for model "%s" is missing "%s".',
                $model,
                $key,
            ));
        }

        return self::validatePrice($model, $entry[$key], $key);
    }

    /**
     * @param  array<mixed>  $entry
     */
    private static function optionalPrice(string $model, array $entry, string $key): ?float
    {
        if (! array_key_exists($key, $entry) || $entry[$key] === null) {
            return null;
        }

        return self::validatePrice($model, $entry[$key], $key);
    }

    private static function validatePrice(string $model, mixed $value, string $key): float
    {
        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException(sprintf(
                'Pricing entry for model "%s" has non-numeric "%s".',
                $model,
                $key,
            ));
        }

        $asFloat = (float) $value;

        if ($asFloat < 0.0) {
            throw new InvalidArgumentException(sprintf(
                'Pricing entry for model "%s" has negative "%s".',
                $model,
                $key,
            ));
        }

        return $asFloat;
    }

    private static function assertNonNegative(string $label, int $value): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException(sprintf(
                '%s must be non-negative, got %d.',
                $label,
                $value,
            ));
        }
    }
}
