<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Pulse;

use Laravel\Pulse\Facades\Pulse;
use Mosaiqo\Proofread\Events\EvalRunPersisted;

/**
 * Records persisted eval runs as Laravel Pulse metrics so they
 * appear on the Pulse dashboard alongside queries, jobs, and
 * requests.
 *
 * Registered conditionally by ProofreadServiceProvider only when
 * laravel/pulse is installed.
 */
final class EvalPulseRecorder
{
    public function handle(EvalRunPersisted $event): void
    {
        $run = $event->run;

        $timestamp = $run->created_at?->getTimestamp();

        $statusKey = $run->passed ? 'passed' : 'failed';

        Pulse::record(
            type: 'proofread_eval',
            key: $run->dataset_name.'::'.$statusKey,
            value: 1,
            timestamp: $timestamp,
        )->count();

        Pulse::record(
            type: 'proofread_eval_duration',
            key: $run->dataset_name,
            value: (int) round($run->duration_ms),
            timestamp: $timestamp,
        )->avg()->max();

        if ($run->total_cost_usd !== null) {
            Pulse::record(
                type: 'proofread_eval_cost',
                key: $run->dataset_name,
                value: (int) round($run->total_cost_usd * 1_000_000),
                timestamp: $timestamp,
            )->sum();
        }
    }
}
