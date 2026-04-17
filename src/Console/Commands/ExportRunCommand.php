<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Database\Eloquent\Collection;
use Mosaiqo\Proofread\Models\EvalComparison;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;
use Mosaiqo\Proofread\Proofread;
use Mosaiqo\Proofread\Support\ComparisonResolver;
use Mosaiqo\Proofread\Support\RunResolver;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Export a persisted eval run or comparison as a self-contained Markdown
 * or HTML document.
 *
 * Accepted reference forms for the {run} argument:
 *   - Full ULID (26 chars).
 *   - Commit SHA prefix (4-40 hex chars).
 *   - Literal "latest".
 *
 * Use --type to disambiguate between runs and comparisons when the
 * identifier could match both.
 */
final class ExportRunCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'evals:export
        {run : Run or comparison reference (ULID, commit SHA, or "latest")}
        {--format=md : Output format: md or html}
        {--output= : Write the export to this path instead of stdout}
        {--type=auto : Subject type: auto, run, or comparison}';

    /**
     * @var string
     */
    protected $description = 'Export a persisted eval run or comparison as a shareable Markdown or HTML document.';

    public function handle(
        RunResolver $runResolver,
        ComparisonResolver $comparisonResolver,
        ViewFactory $views,
    ): int {
        $runArg = $this->argument('run');
        $reference = is_string($runArg) ? $runArg : '';

        $type = $this->resolveType();
        if ($type === null) {
            return 2;
        }

        $subject = $this->resolveSubject($reference, $type, $runResolver, $comparisonResolver);
        if ($subject === null) {
            $this->error(sprintf('Could not resolve run from reference "%s".', $reference));

            return 2;
        }

        $format = $this->resolveFormat();
        if ($format === null) {
            return 2;
        }

        $rendered = $subject instanceof EvalComparison
            ? $this->renderComparison($subject, $format, $views)
            : $this->renderRun($subject, $format, $views);

        $outputOption = $this->option('output');
        $outputPath = is_string($outputOption) && $outputOption !== '' ? $outputOption : null;

        if ($outputPath !== null) {
            Proofread::writeFile($outputPath, $rendered);
            $this->line(sprintf('Export written to: %s', $outputPath));

            return 0;
        }

        $this->output->writeln($rendered, OutputInterface::OUTPUT_RAW);

        return 0;
    }

    private function resolveSubject(
        string $reference,
        string $type,
        RunResolver $runResolver,
        ComparisonResolver $comparisonResolver,
    ): EvalRun|EvalComparison|null {
        if ($type === 'run') {
            return $runResolver->resolve($reference);
        }

        if ($type === 'comparison') {
            return $comparisonResolver->resolve($reference);
        }

        $run = $runResolver->resolve($reference);
        if ($run !== null) {
            return $run;
        }

        return $comparisonResolver->resolve($reference);
    }

    private function resolveType(): ?string
    {
        $type = $this->option('type');
        $type = is_string($type) ? strtolower($type) : 'auto';

        if ($type !== 'auto' && $type !== 'run' && $type !== 'comparison') {
            $this->error(sprintf('Unsupported --type value "%s". Use "auto", "run", or "comparison".', $type));

            return null;
        }

        return $type;
    }

    private function resolveFormat(): ?string
    {
        $format = $this->option('format');
        $format = is_string($format) ? strtolower($format) : 'md';

        if ($format !== 'md' && $format !== 'html') {
            $this->error(sprintf('Unsupported --format value "%s". Use "md" or "html".', $format));

            return null;
        }

        return $format;
    }

    private function renderRun(EvalRun $run, string $format, ViewFactory $views): string
    {
        $run->loadMissing('datasetVersion');
        /** @var Collection<int, EvalResult> $results */
        $results = EvalResult::query()
            ->where('run_id', $run->id)
            ->orderBy('case_index')
            ->get();

        $view = $format === 'html' ? 'proofread::exports.run.html' : 'proofread::exports.run.md';

        return $views->make($view, [
            'run' => $run,
            'results' => $results,
            'datasetVersionChecksum' => $run->datasetVersion?->checksum,
            'proofreadVersion' => Proofread::VERSION,
            'generatedAt' => gmdate('Y-m-d H:i:s').' UTC',
            'truncate' => $this->truncator(),
        ])->render();
    }

    private function renderComparison(EvalComparison $comparison, string $format, ViewFactory $views): string
    {
        $comparison->loadMissing(['datasetVersion', 'runs.results']);

        $matrixRows = $this->buildMatrixRows($comparison);

        $view = $format === 'html' ? 'proofread::exports.comparison.html' : 'proofread::exports.comparison.md';

        return $views->make($view, [
            'comparison' => $comparison,
            'matrixRows' => $matrixRows,
            'bestByPassRate' => $comparison->bestByPassRate(),
            'cheapest' => $comparison->cheapest(),
            'fastest' => $comparison->fastest(),
            'datasetVersionChecksum' => $comparison->datasetVersion?->checksum,
            'proofreadVersion' => Proofread::VERSION,
            'generatedAt' => gmdate('Y-m-d H:i:s').' UTC',
        ])->render();
    }

    /**
     * @return list<array{index: int, name: string, cells: list<array{subject_label: string, passed: bool, error: bool}>}>
     */
    private function buildMatrixRows(EvalComparison $comparison): array
    {
        $firstRun = $comparison->runs->first();
        if ($firstRun === null) {
            return [];
        }

        $caseNames = [];
        foreach ($firstRun->results as $result) {
            $caseNames[(int) $result->case_index] = $result->case_name ?? ('case '.$result->case_index);
        }
        ksort($caseNames);

        $rows = [];
        foreach ($caseNames as $index => $name) {
            $cells = [];
            foreach ($comparison->runs as $run) {
                $result = $run->results->firstWhere('case_index', $index);
                $cells[] = [
                    'subject_label' => (string) ($run->subject_label ?? ''),
                    'passed' => $result !== null ? (bool) $result->passed : false,
                    'error' => $result?->error_class !== null,
                ];
            }
            $rows[] = [
                'index' => (int) $index,
                'name' => (string) $name,
                'cells' => $cells,
            ];
        }

        return $rows;
    }

    private function truncator(): Closure
    {
        return static function (string $value, int $limit): string {
            if (strlen($value) <= $limit) {
                return $value;
            }

            return substr($value, 0, $limit).'... (truncated)';
        };
    }
}
