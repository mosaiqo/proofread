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

    /**
     * Returns the assertions to run against a specific case.
     *
     * Override to read per-case metadata (e.g. $case['meta']['expected_count'])
     * and compose assertions that vary per case. The default delegates to the
     * shared assertions() list.
     *
     * Invoked once per case at run time. The CLI header no longer invokes
     * this method — override detection is performed via reflection to
     * preserve single-pass execution. Implementations should still be
     * cheap: avoid container-heavy work or I/O here; construct lightweight
     * assertion objects and defer expensive wiring to the assertion's own
     * run() method.
     *
     * @param  array<string, mixed>  $case
     * @return array<int, Assertion>
     */
    public function assertionsFor(array $case): array
    {
        unset($case);

        return $this->assertions();
    }

    public function name(): string
    {
        return static::class;
    }

    /**
     * Lifecycle hook invoked before dataset/subject/assertions are read.
     * Override to set up database state, tenant context, or other
     * prerequisites that the suite's data depends on.
     */
    public function setUp(): void
    {
        //
    }

    /**
     * Lifecycle hook invoked after the suite finishes running.
     * Called in a finally block so it runs even if the subject throws.
     */
    public function tearDown(): void
    {
        //
    }
}
