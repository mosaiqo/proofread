<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Suite;

use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\Dataset;

abstract class EvalSuite
{
    abstract public function dataset(): Dataset;

    /**
     * Returns the subject under evaluation.
     *
     * Accepted shapes: a callable, a class-string of an Agent, or an Agent
     * instance. Type validation is performed by the runner, not by the suite.
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
