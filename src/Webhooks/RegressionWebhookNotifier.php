<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Webhooks;

use Illuminate\Http\Client\Factory as HttpFactory;
use InvalidArgumentException;
use Mosaiqo\Proofread\Diff\CaseDelta;
use Mosaiqo\Proofread\Diff\EvalRunDelta;
use Mosaiqo\Proofread\Events\EvalRunRegressed;

/**
 * Posts regression alerts to one or more HTTP webhooks.
 *
 * Each configured webhook is an entry shaped {url: string, format: string}.
 * Supported formats are "slack" (Block Kit), "discord" (embeds), and
 * "generic" (plain JSON mirroring the MCP get_eval_run_diff payload shape).
 */
final class RegressionWebhookNotifier
{
    /**
     * @param  array<string, array{url: string, format: string}>  $webhooks
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $webhooks,
    ) {}

    public function notify(EvalRunRegressed $event): void
    {
        foreach ($this->webhooks as $config) {
            $payload = $this->formatPayload($event, $config['format']);
            $this->http->post($config['url'], $payload);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPayload(EvalRunRegressed $event, string $format): array
    {
        return match ($format) {
            'slack' => $this->slackPayload($event),
            'discord' => $this->discordPayload($event),
            'generic' => $this->genericPayload($event),
            default => throw new InvalidArgumentException("Unknown webhook format: {$format}"),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function slackPayload(EvalRunRegressed $event): array
    {
        $delta = $event->delta;

        return [
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => "Eval regression detected: {$delta->datasetName}",
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        ['type' => 'mrkdwn', 'text' => "*Regressions:*\n{$delta->regressions}"],
                        ['type' => 'mrkdwn', 'text' => "*Improvements:*\n{$delta->improvements}"],
                        ['type' => 'mrkdwn', 'text' => "*Cost delta:*\n".$this->formatCost($delta->costDeltaUsd)],
                        ['type' => 'mrkdwn', 'text' => "*Duration delta:*\n".$this->formatDuration($delta->durationDeltaMs)],
                    ],
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "base: `{$event->baseRun->id}` -> head: `{$event->headRun->id}`",
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function discordPayload(EvalRunRegressed $event): array
    {
        $delta = $event->delta;

        return [
            'embeds' => [
                [
                    'title' => "Eval regression: {$delta->datasetName}",
                    'color' => 0xE74C3C,
                    'fields' => [
                        ['name' => 'Regressions', 'value' => (string) $delta->regressions, 'inline' => true],
                        ['name' => 'Improvements', 'value' => (string) $delta->improvements, 'inline' => true],
                        ['name' => 'Cost delta', 'value' => $this->formatCost($delta->costDeltaUsd), 'inline' => true],
                        ['name' => 'Duration delta', 'value' => $this->formatDuration($delta->durationDeltaMs), 'inline' => true],
                        ['name' => 'Base run', 'value' => $event->baseRun->id, 'inline' => false],
                        ['name' => 'Head run', 'value' => $event->headRun->id, 'inline' => false],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function genericPayload(EvalRunRegressed $event): array
    {
        return $this->serializeDelta($event->delta);
    }

    /**
     * Mirrors the shape produced by the `get_eval_run_diff` MCP tool and the
     * `evals:compare --format=json` CLI so consumers can share a single
     * parser across transports.
     *
     * @return array<string, mixed>
     */
    private function serializeDelta(EvalRunDelta $delta): array
    {
        return [
            'base_run_id' => $delta->baseRunId,
            'head_run_id' => $delta->headRunId,
            'dataset_name' => $delta->datasetName,
            'total_cases' => $delta->totalCases,
            'regressions' => $delta->regressions,
            'improvements' => $delta->improvements,
            'stable_passes' => $delta->stablePasses,
            'stable_failures' => $delta->stableFailures,
            'cost_delta_usd' => $delta->costDeltaUsd,
            'duration_delta_ms' => $delta->durationDeltaMs,
            'has_regressions' => $delta->hasRegressions(),
            'cases' => array_map(
                fn (CaseDelta $case): array => $this->serializeCase($case),
                $delta->cases,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCase(CaseDelta $case): array
    {
        return [
            'case_index' => $case->caseIndex,
            'case_name' => $case->caseName,
            'status' => $case->status,
            'base_passed' => $case->basePassed,
            'head_passed' => $case->headPassed,
            'base_cost_usd' => $case->baseCostUsd,
            'head_cost_usd' => $case->headCostUsd,
            'base_duration_ms' => $case->baseDurationMs,
            'head_duration_ms' => $case->headDurationMs,
            'new_failures' => $case->newFailures,
            'fixed_failures' => $case->fixedFailures,
        ];
    }

    private function formatCost(float $delta): string
    {
        $sign = $delta >= 0 ? '+' : '-';

        return sprintf('%s$%s', $sign, number_format(abs($delta), 4, '.', ''));
    }

    private function formatDuration(float $delta): string
    {
        $sign = $delta >= 0 ? '+' : '-';

        return sprintf('%s%sms', $sign, number_format(abs($delta), 1, '.', ''));
    }
}
