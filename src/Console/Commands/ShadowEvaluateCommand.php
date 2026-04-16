<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Models\ShadowEval;
use Mosaiqo\Proofread\Shadow\ShadowEvaluationSummary;
use Mosaiqo\Proofread\Shadow\ShadowEvaluator;

final class ShadowEvaluateCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'shadow:evaluate
        {--agent= : Filter by agent FQCN}
        {--since= : Only evaluate captures captured since this duration (e.g. 1h, 24h, 7d)}
        {--batch=100 : Maximum captures to process in a single run}
        {--force : Re-evaluate captures that already have a ShadowEval}
        {--dry-run : Do not persist evaluations; report what would be done}';

    /**
     * @var string
     */
    protected $description = 'Evaluate shadow captures against their registered assertions.';

    public function handle(ShadowEvaluator $evaluator): int
    {
        if (config('proofread.shadow.enabled') !== true) {
            $this->warn('Shadow capture is disabled. Set proofread.shadow.enabled=true to use this command.');

            return 0;
        }

        $agentOption = $this->option('agent');
        $agent = is_string($agentOption) && $agentOption !== '' ? $agentOption : null;

        $sinceOption = $this->option('since');
        $since = is_string($sinceOption) && $sinceOption !== '' ? $sinceOption : null;

        $batchOption = $this->option('batch');
        $batch = is_numeric($batchOption) ? (int) $batchOption : 100;
        if ($batch < 1) {
            $batch = 100;
        }

        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        try {
            $sinceTimestamp = $since !== null ? $this->parseSince($since) : null;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return 2;
        }

        $query = $this->buildQuery($agent, $sinceTimestamp, $force);
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->line('No captures to evaluate.');

            return 0;
        }

        $this->line(sprintf('Evaluating %d captures...', $total));

        $query->limit($batch);
        $captures = $query->get();
        $toProcess = $captures->count();

        if ($dryRun) {
            DB::beginTransaction();
        }

        $summary = $evaluator->evaluate($captures, $force);

        if ($dryRun) {
            DB::rollBack();
            $this->line('DRY RUN — no evaluations persisted.');
        }

        $this->printSummary($summary, $toProcess);

        return 0;
    }

    private function parseSince(string $since): Carbon
    {
        $expanded = $this->expandDurationShorthand($since);
        $timestamp = strtotime('-'.$expanded);

        if ($timestamp === false) {
            throw new InvalidArgumentException(
                sprintf('Unable to parse --since value "%s". Examples: 1h, 24h, 7d.', $since)
            );
        }

        return Carbon::createFromTimestamp($timestamp);
    }

    private function expandDurationShorthand(string $since): string
    {
        $units = [
            's' => 'seconds',
            'm' => 'minutes',
            'h' => 'hours',
            'd' => 'days',
            'w' => 'weeks',
        ];

        if (preg_match('/^(\d+)\s*([smhdw])$/i', trim($since), $matches) !== 1) {
            return $since;
        }

        $amount = $matches[1];
        $unit = strtolower($matches[2]);

        return $amount.' '.$units[$unit];
    }

    /**
     * @return Builder<ShadowCapture>
     */
    private function buildQuery(?string $agent, ?Carbon $since, bool $force): Builder
    {
        $query = ShadowCapture::query();

        if ($agent !== null) {
            $query->where('agent_class', $agent);
        }

        if ($since !== null) {
            $query->where('captured_at', '>=', $since);
        }

        if (! $force) {
            $query->whereNotIn(
                'id',
                ShadowEval::query()->select('capture_id'),
            );
        }

        $query->orderBy('captured_at', 'asc');

        return $query;
    }

    private function printSummary(ShadowEvaluationSummary $summary, int $processed): void
    {
        $passRatePct = $summary->passRate() * 100.0;

        $this->line('');
        $this->line('Summary:');
        $this->line(sprintf('  Processed:  %d', $processed));
        $this->line(sprintf('  Skipped:    %d (no assertions configured)', $summary->capturesSkipped));
        $this->line(sprintf('  Evals:      %d created', $summary->evalsCreated));
        $this->line(sprintf(
            '  Pass rate:  %.1f%% (%d passed, %d failed)',
            $passRatePct,
            $summary->passed,
            $summary->failed,
        ));
        $this->line(sprintf('  Judge cost: $%.4f', $summary->totalJudgeCostUsd));
        $this->line(sprintf('  Duration:   %.0fms', $summary->durationMs));
    }
}
