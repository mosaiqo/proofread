<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Suite;

use Laravel\Ai\Contracts\Agent;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\Dataset;

abstract class EvalSuite
{
    abstract public function dataset(): Dataset;

    /**
     * Returns the subject under evaluation.
     *
     * Can be one of:
     * - A {@see \Closure} / callable — invoked as `fn (mixed $input, array $case): mixed`
     *   where `$input` is `$case['input']` pre-unwrapped and `$case` is the full case
     *   array (including `expected`, `meta`, etc.).
     * - A class-string FQCN of a class implementing {@see Agent}
     *   — resolved from the container and invoked with the case input as the prompt.
     * - An instance of an Agent — invoked as above.
     *
     * For callables with multiple named inputs, have each case's `input` be an
     * associative array and unwrap inside the closure:
     *
     * ```php
     * public function subject(): mixed
     * {
     *     return fn (array $input): string =>
     *         $this->generator->generate($input['agent'], $input['task']);
     * }
     * ```
     *
     * Type validation is performed by the runner, not by the suite.
     */
    abstract public function subject(): mixed;

    /**
     * @return array<int, Assertion>
     */
    abstract public function assertions(): array;

    public function name(): string
    {
        return static::class;
    }
}
