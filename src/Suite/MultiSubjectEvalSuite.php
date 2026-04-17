<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Suite;

use LogicException;

/**
 * Abstract base for suites that evaluate the same dataset against multiple
 * subjects (typically different models, providers, or prompt variations).
 *
 * Implementers override subjects() returning a map of label -> subject.
 * Labels become column headers in comparison reports. Subjects follow the
 * same contract as EvalSuite::subject() -- callables, Agent FQCNs, or
 * Agent instances.
 *
 * When run through a legacy single-subject runner, behaves as a regular
 * EvalSuite by returning the first subject from subjects().
 */
abstract class MultiSubjectEvalSuite extends EvalSuite
{
    /**
     * @return array<string, callable|class-string|object>
     */
    abstract public function subjects(): array;

    /**
     * Returns the first subject from subjects() for backward compatibility
     * with runners that only understand single-subject suites.
     */
    final public function subject(): mixed
    {
        $subjects = $this->subjects();

        if ($subjects === []) {
            throw new LogicException(
                sprintf('MultiSubjectEvalSuite [%s] declares no subjects.', static::class),
            );
        }

        return array_values($subjects)[0];
    }
}
