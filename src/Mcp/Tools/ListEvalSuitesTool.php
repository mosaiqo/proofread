<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Mcp\Tools;

use Closure;
use Illuminate\Container\Container;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Throwable;

/**
 * MCP tool that lists all Proofread EvalSuite classes configured as
 * discoverable through config('proofread.mcp.suites').
 */
final class ListEvalSuitesTool extends Tool
{
    protected string $name = 'list_eval_suites';

    protected string $description = 'List all Proofread EvalSuite classes available for evaluation.';

    public function handle(): ResponseFactory
    {
        return Response::structured($this->handlePayload());
    }

    /**
     * @return array{suites: list<array{name: string, class: class-string<EvalSuite>, dataset: string, case_count: int, subject: string}>}
     */
    public function handlePayload(): array
    {
        /** @var mixed $configured */
        $configured = config('proofread.mcp.suites', []);
        if (! is_array($configured)) {
            $configured = [];
        }

        $container = Container::getInstance();
        $entries = [];
        foreach ($configured as $fqcn) {
            if (! is_string($fqcn) || $fqcn === '') {
                continue;
            }

            $entry = $this->describeSuite($container, $fqcn);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return ['suites' => $entries];
    }

    /**
     * @return array{name: string, class: class-string<EvalSuite>, dataset: string, case_count: int, subject: string}|null
     */
    private function describeSuite(Container $container, string $fqcn): ?array
    {
        if (! class_exists($fqcn)) {
            return null;
        }

        if (! is_subclass_of($fqcn, EvalSuite::class)) {
            return null;
        }

        try {
            /** @var EvalSuite $suite */
            $suite = $container->make($fqcn);
        } catch (Throwable) {
            return null;
        }

        $dataset = $suite->dataset();

        return [
            'name' => $suite->name(),
            'class' => $fqcn,
            'dataset' => $dataset->name,
            'case_count' => $dataset->count(),
            'subject' => $this->describeSubject($suite),
        ];
    }

    private function describeSubject(EvalSuite $suite): string
    {
        $subject = $suite->subject();

        if ($subject instanceof Closure) {
            return 'callable';
        }

        if (is_string($subject) && class_exists($subject)) {
            return $subject;
        }

        if (is_object($subject)) {
            return $subject::class;
        }

        if (is_callable($subject)) {
            return 'callable';
        }

        return get_debug_type($subject);
    }
}
