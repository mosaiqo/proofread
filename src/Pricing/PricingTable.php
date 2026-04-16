<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Pricing;

use InvalidArgumentException;

final readonly class PricingTable
{
    /**
     * @param  array<string, array{input_per_1m: float, output_per_1m: float}>  $models
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
            ];
        }

        return new self($normalized);
    }

    public function has(string $model): bool
    {
        return array_key_exists($model, $this->models);
    }

    public function cost(string $model, int $tokensIn, int $tokensOut): ?float
    {
        if ($tokensIn < 0) {
            throw new InvalidArgumentException(sprintf(
                'Input tokens must be non-negative, got %d.',
                $tokensIn,
            ));
        }

        if ($tokensOut < 0) {
            throw new InvalidArgumentException(sprintf(
                'Output tokens must be non-negative, got %d.',
                $tokensOut,
            ));
        }

        if (! $this->has($model)) {
            return null;
        }

        $entry = $this->models[$model];
        $cost = ($tokensIn / 1_000_000) * $entry['input_per_1m']
            + ($tokensOut / 1_000_000) * $entry['output_per_1m'];

        return round($cost, 6);
    }

    /**
     * @return array<string, array{input_per_1m: float, output_per_1m: float}>
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

        $value = $entry[$key];

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
}
