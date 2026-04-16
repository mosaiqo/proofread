<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Assertions;

use InvalidArgumentException;
use Laravel\Ai\Responses\TextResponse;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

final readonly class Trajectory implements Assertion
{
    private const MODE_MAX_STEPS = 'max_steps';

    private const MODE_MIN_STEPS = 'min_steps';

    private const MODE_STEPS_BETWEEN = 'steps_between';

    private const MODE_CALLS_TOOL = 'calls_tool';

    private const MODE_DOES_NOT_CALL_TOOL = 'does_not_call_tool';

    private const MODE_CALLS_TOOLS = 'calls_tools';

    private const MODE_CALLS_TOOLS_IN_ORDER = 'calls_tools_in_order';

    /**
     * @param  array<int, string>  $tools
     */
    private function __construct(
        private string $mode,
        private ?int $min = null,
        private ?int $max = null,
        private ?string $tool = null,
        private array $tools = [],
    ) {}

    public static function maxSteps(int $max): self
    {
        self::guardNonNegative('max', $max);

        return new self(self::MODE_MAX_STEPS, max: $max);
    }

    public static function minSteps(int $min): self
    {
        self::guardNonNegative('min', $min);

        return new self(self::MODE_MIN_STEPS, min: $min);
    }

    public static function stepsBetween(int $min, int $max): self
    {
        self::guardNonNegative('min', $min);
        self::guardNonNegative('max', $max);

        if ($min > $max) {
            throw new InvalidArgumentException(
                sprintf('Minimum steps %d cannot exceed maximum steps %d', $min, $max)
            );
        }

        return new self(self::MODE_STEPS_BETWEEN, min: $min, max: $max);
    }

    public static function callsTool(string $name): self
    {
        self::guardNonEmptyName($name);

        return new self(self::MODE_CALLS_TOOL, tool: $name);
    }

    public static function doesNotCallTool(string $name): self
    {
        self::guardNonEmptyName($name);

        return new self(self::MODE_DOES_NOT_CALL_TOOL, tool: $name);
    }

    /**
     * @param  array<int, string>  $names
     */
    public static function callsTools(array $names): self
    {
        self::guardToolList($names);

        return new self(self::MODE_CALLS_TOOLS, tools: array_values($names));
    }

    /**
     * @param  array<int, string>  $names
     */
    public static function callsToolsInOrder(array $names): self
    {
        self::guardToolList($names);

        return new self(self::MODE_CALLS_TOOLS_IN_ORDER, tools: array_values($names));
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        unset($output);

        $raw = $context['raw'] ?? null;

        if (! $raw instanceof TextResponse) {
            return AssertionResult::fail(sprintf(
                'Trajectory requires an Agent subject — got %s',
                get_debug_type($raw),
            ));
        }

        $stepCount = $raw->steps->count();
        /** @var array<int, string> $toolCallNames */
        $toolCallNames = $raw->toolCalls
            ->map(fn (object $call): string => self::extractToolName($call))
            ->filter(fn (string $name): bool => $name !== '')
            ->values()
            ->all();

        return match ($this->mode) {
            self::MODE_MAX_STEPS => $this->evaluateMaxSteps($stepCount),
            self::MODE_MIN_STEPS => $this->evaluateMinSteps($stepCount),
            self::MODE_STEPS_BETWEEN => $this->evaluateStepsBetween($stepCount),
            self::MODE_CALLS_TOOL => $this->evaluateCallsTool($toolCallNames),
            self::MODE_DOES_NOT_CALL_TOOL => $this->evaluateDoesNotCallTool($toolCallNames),
            self::MODE_CALLS_TOOLS => $this->evaluateCallsTools($toolCallNames),
            self::MODE_CALLS_TOOLS_IN_ORDER => $this->evaluateCallsToolsInOrder($toolCallNames),
            default => AssertionResult::fail(sprintf('Unknown trajectory mode: %s', $this->mode)),
        };
    }

    public function name(): string
    {
        return 'trajectory';
    }

    private function evaluateMaxSteps(int $stepCount): AssertionResult
    {
        $max = (int) $this->max;

        if ($stepCount > $max) {
            return AssertionResult::fail(sprintf(
                'Trajectory used %d steps, exceeding the limit of %d',
                $stepCount,
                $max,
            ));
        }

        return AssertionResult::pass(sprintf(
            'Trajectory used %d steps, within the limit of %d',
            $stepCount,
            $max,
        ));
    }

    private function evaluateMinSteps(int $stepCount): AssertionResult
    {
        $min = (int) $this->min;

        if ($stepCount < $min) {
            return AssertionResult::fail(sprintf(
                'Trajectory used %d steps, below the minimum of %d',
                $stepCount,
                $min,
            ));
        }

        return AssertionResult::pass(sprintf(
            'Trajectory used %d steps, meeting the minimum of %d',
            $stepCount,
            $min,
        ));
    }

    private function evaluateStepsBetween(int $stepCount): AssertionResult
    {
        $min = (int) $this->min;
        $max = (int) $this->max;

        if ($stepCount < $min || $stepCount > $max) {
            return AssertionResult::fail(sprintf(
                'Trajectory used %d steps, outside the allowed range [%d, %d]',
                $stepCount,
                $min,
                $max,
            ));
        }

        return AssertionResult::pass(sprintf(
            'Trajectory used %d steps, within the allowed range [%d, %d]',
            $stepCount,
            $min,
            $max,
        ));
    }

    /**
     * @param  array<int, string>  $toolCallNames
     */
    private function evaluateCallsTool(array $toolCallNames): AssertionResult
    {
        $tool = (string) $this->tool;

        if (! in_array($tool, $toolCallNames, true)) {
            return AssertionResult::fail(sprintf(
                'Trajectory did not call required tool "%s" (observed: %s)',
                $tool,
                $this->describeObservedTools($toolCallNames),
            ));
        }

        return AssertionResult::pass(sprintf(
            'Trajectory called required tool "%s"',
            $tool,
        ));
    }

    /**
     * @param  array<int, string>  $toolCallNames
     */
    private function evaluateDoesNotCallTool(array $toolCallNames): AssertionResult
    {
        $tool = (string) $this->tool;

        if (in_array($tool, $toolCallNames, true)) {
            return AssertionResult::fail(sprintf(
                'Trajectory called forbidden tool "%s"',
                $tool,
            ));
        }

        return AssertionResult::pass(sprintf(
            'Trajectory did not call forbidden tool "%s"',
            $tool,
        ));
    }

    /**
     * @param  array<int, string>  $toolCallNames
     */
    private function evaluateCallsTools(array $toolCallNames): AssertionResult
    {
        $missing = array_values(array_diff($this->tools, $toolCallNames));

        if ($missing !== []) {
            return AssertionResult::fail(sprintf(
                'Trajectory did not call required tools: %s (observed: %s)',
                implode(', ', $missing),
                $this->describeObservedTools($toolCallNames),
            ));
        }

        return AssertionResult::pass(sprintf(
            'Trajectory called all required tools: %s',
            implode(', ', $this->tools),
        ));
    }

    /**
     * @param  array<int, string>  $toolCallNames
     */
    private function evaluateCallsToolsInOrder(array $toolCallNames): AssertionResult
    {
        $pointer = 0;
        $required = $this->tools;
        $count = count($required);

        foreach ($toolCallNames as $called) {
            if ($pointer < $count && $called === $required[$pointer]) {
                $pointer++;
            }
        }

        if ($pointer < $count) {
            return AssertionResult::fail(sprintf(
                'Trajectory did not call tools in required order; missing "%s" after position %d (observed: %s)',
                $required[$pointer],
                $pointer,
                $this->describeObservedTools($toolCallNames),
            ));
        }

        return AssertionResult::pass(sprintf(
            'Trajectory called required tools in order: %s',
            implode(' -> ', $required),
        ));
    }

    /**
     * @param  array<int, string>  $toolCallNames
     */
    private function describeObservedTools(array $toolCallNames): string
    {
        if ($toolCallNames === []) {
            return '(none)';
        }

        return implode(', ', $toolCallNames);
    }

    private static function extractToolName(object $call): string
    {
        if (property_exists($call, 'name')) {
            /** @var mixed $name */
            $name = $call->name;

            return is_string($name) ? $name : '';
        }

        return '';
    }

    private static function guardNonNegative(string $label, int $value): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException(sprintf(
                'Trajectory %s must be non-negative, got %d',
                $label,
                $value,
            ));
        }
    }

    private static function guardNonEmptyName(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Trajectory tool name cannot be empty');
        }
    }

    /**
     * @param  array<int, mixed>  $names
     */
    private static function guardToolList(array $names): void
    {
        if ($names === []) {
            throw new InvalidArgumentException('Trajectory tool list cannot be empty');
        }

        foreach ($names as $name) {
            if (! is_string($name)) {
                throw new InvalidArgumentException(sprintf(
                    'Trajectory tool names must be strings, got %s',
                    get_debug_type($name),
                ));
            }

            if ($name === '') {
                throw new InvalidArgumentException('Trajectory tool names cannot be empty');
            }
        }
    }
}
