<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Runner\Concurrency;

/**
 * Executes tasks sequentially in-process. Useful for tests and as a safe
 * fallback when real concurrency drivers are unavailable. Preserves
 * insertion order.
 */
final class SyncConcurrencyDriver implements ConcurrencyDriver
{
    /** @var int<0, max> */
    public int $invocations = 0;

    /** @var list<int> */
    public array $taskCountPerInvocation = [];

    public function run(array $tasks): array
    {
        $this->invocations++;
        $this->taskCountPerInvocation[] = count($tasks);

        $results = [];
        foreach ($tasks as $key => $task) {
            $results[$key] = $task();
        }

        return $results;
    }
}
