<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use Mosaiqo\Proofread\Export\EvalComparisonExporter;
use Mosaiqo\Proofread\Export\EvalRunExporter;
use Mosaiqo\Proofread\Models\EvalComparison;
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
        EvalRunExporter $runExporter,
        EvalComparisonExporter $comparisonExporter,
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
            ? $comparisonExporter->render($subject, $format)
            : $runExporter->render($subject, $format);

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
}
