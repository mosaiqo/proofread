<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Telescope;

use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Mosaiqo\Proofread\Events\EvalRunPersisted;

/**
 * Records persisted eval runs as Telescope entries so they appear
 * alongside queries, jobs, and requests in the Telescope UI.
 *
 * Registered conditionally by ProofreadServiceProvider only when
 * laravel/telescope is installed.
 */
final class EvalRunWatcher
{
    public function handle(EvalRunPersisted $event): void
    {
        if (! class_exists(Telescope::class)) {
            return;
        }

        if (! Telescope::isRecording()) {
            return;
        }

        $run = $event->run;

        $tags = ['proofread_eval', 'dataset:'.$run->dataset_name];
        if (is_string($run->suite_class) && $run->suite_class !== '') {
            $tags[] = 'suite:'.$run->suite_class;
        }
        if (is_string($run->commit_sha) && $run->commit_sha !== '') {
            $tags[] = 'commit:'.$run->commit_sha;
        }
        $tags[] = $run->passed ? 'status:passed' : 'status:failed';

        Telescope::recordEvent(IncomingEntry::make([
            'name' => EvalRunPersisted::class,
            'eval_run_id' => $run->id,
            'dataset_name' => $run->dataset_name,
            'suite_class' => $run->suite_class,
            'subject_type' => $run->subject_type,
            'subject_class' => $run->subject_class,
            'subject_label' => $run->subject_label,
            'passed' => $run->passed,
            'pass_count' => $run->pass_count,
            'fail_count' => $run->fail_count,
            'error_count' => $run->error_count,
            'total_count' => $run->total_count,
            'duration_ms' => $run->duration_ms,
            'total_cost_usd' => $run->total_cost_usd,
            'total_tokens_in' => $run->total_tokens_in,
            'total_tokens_out' => $run->total_tokens_out,
            'model' => $run->model,
            'commit_sha' => $run->commit_sha,
        ])->tags($tags));
    }
}
