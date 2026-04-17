<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Database\Eloquent\Collection;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Proofread;
use Mosaiqo\Proofread\Support\RunResolver;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Export a persisted eval run as a self-contained Markdown or HTML document.
 *
 * Accepted reference forms for the {run} argument:
 *   - Full ULID (26 chars).
 *   - Commit SHA prefix (4-40 hex chars).
 *   - Literal "latest".
 */
final class ExportRunCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'evals:export
        {run : Run reference (ULID, commit SHA, or "latest")}
        {--format=md : Output format: md or html}
        {--output= : Write the export to this path instead of stdout}';

    /**
     * @var string
     */
    protected $description = 'Export a persisted eval run as a shareable Markdown or HTML document.';

    public function handle(RunResolver $resolver, ViewFactory $views): int
    {
        $runArg = $this->argument('run');
        $reference = is_string($runArg) ? $runArg : '';

        $run = $resolver->resolve($reference);
        if ($run === null) {
            $this->error(sprintf('Could not resolve run from reference "%s".', $reference));

            return 2;
        }

        $format = $this->resolveFormat();
        if ($format === null) {
            return 2;
        }

        $run->loadMissing('datasetVersion');
        /** @var Collection<int, EvalResult> $results */
        $results = EvalResult::query()
            ->where('run_id', $run->id)
            ->orderBy('case_index')
            ->get();

        $view = $format === 'html' ? 'proofread::exports.run.html' : 'proofread::exports.run.md';

        $rendered = $views->make($view, [
            'run' => $run,
            'results' => $results,
            'datasetVersionChecksum' => $run->datasetVersion?->checksum,
            'proofreadVersion' => Proofread::VERSION,
            'generatedAt' => gmdate('Y-m-d H:i:s').' UTC',
            'truncate' => $this->truncator(),
        ])->render();

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
