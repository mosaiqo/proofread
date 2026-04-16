<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Mcp\Tools;

use Illuminate\Container\Container;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Mosaiqo\Proofread\Diff\EvalRunDelta;
use Mosaiqo\Proofread\Diff\EvalRunDiff;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * MCP tool that computes a structured diff between two persisted eval runs.
 *
 * The two runs must share the same dataset_name. The returned payload
 * classifies cases as regression / improvement / stable_pass / stable_fail,
 * aggregates cost and duration deltas, and serializes each CaseDelta.
 * When there are more than 50 cases the list is truncated, but all
 * regressions and improvements are always retained.
 */
final class GetEvalRunDiffTool extends Tool
{
    protected string $name = 'get_eval_run_diff';

    protected string $description = 'Compute a structured diff between two persisted eval runs of the same dataset.';

    private const CASE_LIMIT = 50;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'base_run_id' => $schema->string()
                ->description('ULID of the base eval run.')
                ->required(),
            'head_run_id' => $schema->string()
                ->description('ULID of the head eval run.')
                ->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory|Response
    {
        $baseId = $request->get('base_run_id');
        $headId = $request->get('head_run_id');

        if (! is_string($baseId) || $baseId === '') {
            return Response::error('The base_run_id argument is required.');
        }

        if (! is_string($headId) || $headId === '') {
            return Response::error('The head_run_id argument is required.');
        }

        $base = EvalRun::query()->where('id', $baseId)->first();
        if ($base === null) {
            return Response::error(sprintf('Base eval run "%s" not found.', $baseId));
        }

        $head = EvalRun::query()->where('id', $headId)->first();
        if ($head === null) {
            return Response::error(sprintf('Head eval run "%s" not found.', $headId));
        }

        if ($base->dataset_name !== $head->dataset_name) {
            return Response::error(sprintf(
                'Cannot diff runs of different datasets: base=%s, head=%s.',
                $base->dataset_name,
                $head->dataset_name,
            ));
        }

        $diff = Container::getInstance()->make(EvalRunDiff::class);
        $delta = $diff->compute($base, $head);

        return Response::structured($this->buildPayload($delta));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(EvalRunDelta $delta): array
    {
        $payload = $delta->toArray();

        /** @var list<array<string, mixed>> $serializedCases */
        $serializedCases = $payload['cases'];

        [$visibleCases, $truncated, $omitted] = $this->truncateCases($serializedCases);

        $payload['cases'] = $visibleCases;

        if ($truncated) {
            $payload['cases_truncated'] = true;
            $payload['cases_omitted'] = $omitted;
        }

        return $payload;
    }

    /**
     * Truncate the case list to at most CASE_LIMIT entries while ensuring
     * every regression and improvement case is retained. Remaining slots
     * are filled with the other cases in their original order.
     *
     * @param  list<array<string, mixed>>  $cases
     * @return array{0: list<array<string, mixed>>, 1: bool, 2: int}
     */
    private function truncateCases(array $cases): array
    {
        $total = count($cases);
        if ($total <= self::CASE_LIMIT) {
            return [$cases, false, 0];
        }

        $priority = [];
        $rest = [];
        foreach ($cases as $case) {
            $status = $case['status'] ?? null;
            if ($status === 'regression' || $status === 'improvement') {
                $priority[] = $case;
            } else {
                $rest[] = $case;
            }
        }

        $remaining = max(0, self::CASE_LIMIT - count($priority));
        $visible = array_merge($priority, array_slice($rest, 0, $remaining));
        $omitted = $total - count($visible);

        return [$visible, true, $omitted];
    }
}
