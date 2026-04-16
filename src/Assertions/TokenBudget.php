<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Assertions;

use InvalidArgumentException;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

final readonly class TokenBudget implements Assertion
{
    private function __construct(
        public ?int $maxInput,
        public ?int $maxOutput,
        public ?int $maxTotal,
    ) {
        foreach (['maxInput' => $maxInput, 'maxOutput' => $maxOutput, 'maxTotal' => $maxTotal] as $label => $value) {
            if ($value !== null && $value < 0) {
                throw new InvalidArgumentException(
                    sprintf('%s must be >= 0, got %d.', $label, $value)
                );
            }
        }
    }

    public static function maxInput(int $tokens): self
    {
        return new self($tokens, null, null);
    }

    public static function maxOutput(int $tokens): self
    {
        return new self(null, $tokens, null);
    }

    public static function maxTotal(int $tokens): self
    {
        return new self(null, null, $tokens);
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        if ($this->maxInput !== null) {
            return $this->checkSingle($context, 'tokens_in', $this->maxInput, 'Input');
        }

        if ($this->maxOutput !== null) {
            return $this->checkSingle($context, 'tokens_out', $this->maxOutput, 'Output');
        }

        return $this->checkTotal($context, (int) $this->maxTotal);
    }

    public function name(): string
    {
        return 'token_budget';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function checkSingle(array $context, string $key, int $limit, string $label): AssertionResult
    {
        if (! array_key_exists($key, $context)) {
            return AssertionResult::fail(
                sprintf("TokenBudget requires '%s' in context", $key)
            );
        }

        $value = $context[$key];
        if ($value === null) {
            return AssertionResult::fail(
                sprintf("TokenBudget requires '%s' in context; got null (subject may not report token usage)", $key)
            );
        }

        if (! is_int($value)) {
            return AssertionResult::fail(
                sprintf("TokenBudget requires integer '%s' in context, got %s", $key, gettype($value))
            );
        }

        if ($value > $limit) {
            return AssertionResult::fail(
                sprintf('%s tokens %d exceed limit of %d', $label, $value, $limit)
            );
        }

        return AssertionResult::pass(
            sprintf('%s tokens %d within limit of %d', $label, $value, $limit)
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function checkTotal(array $context, int $limit): AssertionResult
    {
        if (array_key_exists('tokens_total', $context) && $context['tokens_total'] !== null) {
            $total = $context['tokens_total'];
            if (! is_int($total)) {
                return AssertionResult::fail(
                    sprintf("TokenBudget requires integer 'tokens_total' in context, got %s", gettype($total))
                );
            }
        } else {
            $hasIn = array_key_exists('tokens_in', $context) && $context['tokens_in'] !== null;
            $hasOut = array_key_exists('tokens_out', $context) && $context['tokens_out'] !== null;
            if (! $hasIn || ! $hasOut) {
                return AssertionResult::fail(
                    "TokenBudget requires 'tokens_total' or both 'tokens_in' and 'tokens_out' in context"
                );
            }
            $in = $context['tokens_in'];
            $out = $context['tokens_out'];
            if (! is_int($in) || ! is_int($out)) {
                return AssertionResult::fail(
                    "TokenBudget requires integer 'tokens_in' and 'tokens_out' in context"
                );
            }
            $total = $in + $out;
        }

        if ($total > $limit) {
            return AssertionResult::fail(
                sprintf('Total tokens %d exceed limit of %d', $total, $limit)
            );
        }

        return AssertionResult::pass(
            sprintf('Total tokens %d within limit of %d', $total, $limit)
        );
    }
}
