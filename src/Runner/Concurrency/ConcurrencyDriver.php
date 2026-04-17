<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Runner\Concurrency;

use Closure;

/**
 * Minimal indirection over Laravel's Concurrency facade so that EvalRunner
 * can be tested without forking processes and so we can swap drivers
 * without touching call sites.
 */
interface ConcurrencyDriver
{
    /**
     * Run the given closures in parallel and return an array of their
     * results, preserving the input key order.
     *
     * @param  array<int, Closure>  $tasks
     * @return array<int, mixed>
     */
    public function run(array $tasks): array;
}
