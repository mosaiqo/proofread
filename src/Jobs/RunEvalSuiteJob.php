<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;
use Mosaiqo\Proofread\Runner\EvalPersister;
use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Suite\EvalSuite;

/**
 * Async execution of an EvalSuite.
 *
 * Large suites with many LLM calls can take minutes or hours, which makes
 * running them inline from a terminal or CI job impractical. This job
 * dispatches the full suite execution (subject + assertions + optional
 * persistence) onto the queue so the caller can return immediately and a
 * worker handles the long-running work out of band.
 *
 * The job intentionally does not retry by default: eval runs can be
 * expensive (real LLM calls, real spend) and a silent retry could double
 * the cost without the operator noticing. Override $tries from the
 * dispatcher if a specific suite is safe to retry.
 */
class RunEvalSuiteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public readonly string $suiteClass,
        public readonly ?string $commitSha = null,
        public readonly bool $persist = true,
    ) {
        $connection = config('proofread.queue.connection');
        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }

        $queue = config('proofread.queue.eval_queue', 'evals');
        $this->onQueue(is_string($queue) && $queue !== '' ? $queue : 'evals');

        $timeout = config('proofread.queue.timeout', 1800);
        $this->timeout = is_int($timeout) ? $timeout : 1800;
    }

    public function handle(EvalRunner $runner, EvalPersister $persister): void
    {
        $suite = $this->resolveSuite();

        $run = $runner->run($suite->subject(), $suite->dataset(), $suite->assertions());

        if ($this->persist) {
            $persister->persist(
                $run,
                suiteClass: $this->suiteClass,
                commitSha: $this->commitSha,
            );
        }
    }

    private function resolveSuite(): EvalSuite
    {
        if (! class_exists($this->suiteClass)) {
            throw new InvalidArgumentException(sprintf(
                "Suite class '%s' not found.",
                $this->suiteClass,
            ));
        }

        if (! is_subclass_of($this->suiteClass, EvalSuite::class)) {
            throw new InvalidArgumentException(sprintf(
                "Class '%s' does not extend %s.",
                $this->suiteClass,
                EvalSuite::class,
            ));
        }

        /** @var EvalSuite $suite */
        $suite = Container::getInstance()->make($this->suiteClass);

        return $suite;
    }
}
