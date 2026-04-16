<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Mosaiqo\Proofread\Diff\CaseDelta;
use Mosaiqo\Proofread\Diff\EvalRunDelta;
use Mosaiqo\Proofread\Diff\EvalRunDiff;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * Compare two persisted eval runs of the same dataset and report the diff.
 *
 * Accepted reference forms for the base/head arguments:
 *   - Full ULID (26 chars) - matched exactly.
 *   - Short or full commit SHA (7-40 hex chars) - matched via prefix
 *     against the commit_sha column, most recent first.
 *   - Literal "latest" - the most recently created run in the DB.
 *
 * Exit codes follow CI conventions: 0 when there are no regressions,
 * 1 when regressions are detected, 2 on argument/resolution errors.
 */
final class CompareEvalsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'evals:compare
        {base : Base run reference (ULID, commit SHA, or "latest")}
        {head : Head run reference (ULID, commit SHA, or "latest")}
        {--format=table : Output format: table or json}
        {--only-regressions : Only show regression cases in the table output}
        {--max-cases=50 : Maximum number of cases to render in the table output}';

    /**
     * @var string
     */
    protected $description = 'Compare two persisted eval runs and report regressions, improvements, and cost/duration deltas.';

    public function handle(EvalRunDiff $diff): int
    {
        $baseArg = $this->argument('base');
        $headArg = $this->argument('head');
        $baseRef = is_string($baseArg) ? $baseArg : '';
        $headRef = is_string($headArg) ? $headArg : '';

        $base = $this->resolveRun($baseRef, 'base');
        if ($base === null) {
            return 2;
        }

        $head = $this->resolveRun($headRef, 'head');
        if ($head === null) {
            return 2;
        }

        if ($base->dataset_name !== $head->dataset_name) {
            $this->error(sprintf(
                'Cannot compare runs of different datasets: base=%s, head=%s.',
                $base->dataset_name,
                $head->dataset_name,
            ));

            return 2;
        }

        try {
            $delta = $diff->compute($base, $head);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return 2;
        }

        $format = $this->resolveFormat();
        if ($format === null) {
            return 2;
        }

        if ($format === 'json') {
            $this->line((string) json_encode(
                $delta->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return $delta->hasRegressions() ? 1 : 0;
        }

        $this->renderTable($base, $head, $delta);

        return $delta->hasRegressions() ? 1 : 0;
    }

    private function resolveRun(string $ref, string $label): ?EvalRun
    {
        if ($ref === '') {
            $this->error(sprintf('The %s argument is required.', $label));

            return null;
        }

        if ($ref === 'latest') {
            /** @var EvalRun|null $run */
            $run = EvalRun::query()->orderByDesc('created_at')->orderByDesc('id')->first();
            if ($run === null) {
                $this->error(sprintf('Could not resolve %s run: no runs found in the database.', $label));

                return null;
            }

            return $run;
        }

        if ($this->looksLikeUlid($ref)) {
            /** @var EvalRun|null $run */
            $run = EvalRun::query()->where('id', $ref)->first();
            if ($run !== null) {
                return $run;
            }
        }

        if ($this->looksLikeCommitSha($ref)) {
            /** @var EvalRun|null $run */
            $run = EvalRun::query()
                ->where('commit_sha', 'like', $ref.'%')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();
            if ($run !== null) {
                return $run;
            }
        }

        $this->error(sprintf('Could not resolve %s run from reference "%s".', $label, $ref));

        return null;
    }

    private function looksLikeUlid(string $ref): bool
    {
        return strlen($ref) === 26 && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $ref) === 1;
    }

    private function looksLikeCommitSha(string $ref): bool
    {
        $len = strlen($ref);

        return $len >= 4 && $len <= 40 && preg_match('/^[0-9a-f]+$/i', $ref) === 1;
    }

    private function resolveFormat(): ?string
    {
        $format = $this->option('format');
        $format = is_string($format) ? $format : 'table';

        if ($format !== 'table' && $format !== 'json') {
            $this->error(sprintf('Unsupported --format value "%s". Use "table" or "json".', $format));

            return null;
        }

        return $format;
    }

    private function renderTable(EvalRun $base, EvalRun $head, EvalRunDelta $delta): void
    {
        $this->line(sprintf('Comparing runs of dataset "%s":', $delta->datasetName));
        $this->line('  base: '.$this->formatRunHeader($base));
        $this->line('  head: '.$this->formatRunHeader($head));
        $this->line('');

        $this->line('Summary:');
        $this->line(sprintf('  Total cases:       %d', $delta->totalCases));
        $this->line($this->formatSummaryLine('Regressions:', $delta->regressions, $delta->regressions > 0 ? 'error' : null));
        $this->line($this->formatSummaryLine('Improvements:', $delta->improvements, $delta->improvements > 0 ? 'info' : null));
        $this->line(sprintf('  Stable passes:     %d', $delta->stablePasses));
        $this->line(sprintf('  Stable failures:   %d', $delta->stableFailures));
        $this->line(sprintf('  Cost delta:        %s', $this->formatCostDelta($delta->costDeltaUsd)));
        $this->line(sprintf('  Duration delta:    %s', $this->formatDurationDelta($delta->durationDeltaMs)));

        $changedCases = $this->selectChangedCases($delta);

        if ($changedCases === [] && ! $delta->hasRegressions() && $delta->improvements === 0) {
            $this->line('');
            $this->line('No differences detected.');

            return;
        }

        $onlyRegressions = (bool) $this->option('only-regressions');
        if ($onlyRegressions) {
            $changedCases = array_values(array_filter(
                $changedCases,
                static fn (CaseDelta $case): bool => $case->status === 'regression',
            ));
        }

        $maxCases = $this->resolveMaxCases();
        $truncated = false;
        if (count($changedCases) > $maxCases) {
            $changedCases = array_slice($changedCases, 0, $maxCases);
            $truncated = true;
        }

        if ($changedCases !== []) {
            $this->line('');
            $this->line('Case-level changes:');
            foreach ($changedCases as $case) {
                $this->renderCase($case);
            }
        }

        if ($truncated) {
            $this->line('');
            $this->line(sprintf('(truncated to --max-cases=%d)', $maxCases));
        }

        $this->line('');
        if ($delta->hasRegressions()) {
            $this->line(sprintf(
                'Overall: %d regression%s detected.',
                $delta->regressions,
                $delta->regressions === 1 ? '' : 's',
            ));
        } else {
            $this->line('Overall: no regressions detected.');
        }
    }

    private function formatSummaryLine(string $label, int $count, ?string $style): string
    {
        $paddedLabel = str_pad($label, 18, ' ', STR_PAD_RIGHT);
        $value = (string) $count;
        if ($style !== null) {
            $value = sprintf('<fg=%s>%s</>', $style === 'error' ? 'red' : 'green', $value);
        }

        return '  '.$paddedLabel.$value;
    }

    private function formatRunHeader(EvalRun $run): string
    {
        $createdAt = $run->created_at?->format('Y-m-d H:i:s') ?? 'unknown';
        $model = $run->model ?? 'unknown';
        $state = $run->passed ? 'passed' : 'failed';

        return sprintf('%s (%s, model %s, %s)', $run->id, $createdAt, $model, $state);
    }

    private function formatCostDelta(float $delta): string
    {
        $sign = $delta >= 0 ? '+' : '-';

        return sprintf('%s$%s', $sign, number_format(abs($delta), 4, '.', ''));
    }

    private function formatDurationDelta(float $delta): string
    {
        $sign = $delta >= 0 ? '+' : '-';

        return sprintf('%s%sms', $sign, number_format(abs($delta), 1, '.', ''));
    }

    /**
     * @return list<CaseDelta>
     */
    private function selectChangedCases(EvalRunDelta $delta): array
    {
        $changed = [];
        foreach ($delta->cases as $case) {
            if ($case->status === 'stable_pass') {
                continue;
            }
            $changed[] = $case;
        }

        return $changed;
    }

    private function renderCase(CaseDelta $case): void
    {
        $label = $this->caseLabel($case);
        $transition = $this->formatStatus($case);

        $this->line(sprintf('  [%s] case_%d  "%s"', $transition, $case->caseIndex, $label));

        if ($case->newFailures !== []) {
            $this->line('    new failures: '.implode(', ', $case->newFailures));
        }

        if ($case->fixedFailures !== []) {
            $this->line('    fixed failures: '.implode(', ', $case->fixedFailures));
        }

        if ($case->status === 'stable_fail'
            && $case->newFailures === []
            && $case->fixedFailures === []) {
            $this->line('    stable_fail, no change');
        }
    }

    private function caseLabel(CaseDelta $case): string
    {
        if (is_string($case->caseName) && $case->caseName !== '') {
            return $case->caseName;
        }

        return 'case_'.$case->caseIndex;
    }

    private function formatStatus(CaseDelta $case): string
    {
        return match ($case->status) {
            'regression' => 'FAIL<-PASS',
            'improvement' => 'PASS<-FAIL',
            'stable_fail' => 'FAIL<-FAIL',
            'stable_pass' => 'PASS<-PASS',
            'base_only' => 'GONE',
            'head_only' => 'NEW',
            default => $case->status,
        };
    }

    private function resolveMaxCases(): int
    {
        $raw = $this->option('max-cases');
        if (is_int($raw)) {
            return max(0, $raw);
        }

        if (is_string($raw) && ctype_digit($raw)) {
            return max(0, (int) $raw);
        }

        return 50;
    }
}
