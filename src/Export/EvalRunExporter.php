<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Export;

use Closure;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;
use Mosaiqo\Proofread\Proofread;

final class EvalRunExporter
{
    public function __construct(
        private readonly ViewFactory $views,
    ) {}

    public function render(EvalRun $run, string $format): string
    {
        if ($format !== 'md' && $format !== 'html') {
            throw new InvalidArgumentException(sprintf(
                'Unsupported export format "%s"; expected "md" or "html".',
                $format,
            ));
        }

        $run->loadMissing('datasetVersion');
        /** @var Collection<int, EvalResult> $results */
        $results = EvalResult::query()
            ->where('run_id', $run->id)
            ->orderBy('case_index')
            ->get();

        $view = $format === 'html' ? 'proofread::exports.run.html' : 'proofread::exports.run.md';

        return $this->views->make($view, [
            'run' => $run,
            'results' => $results,
            'datasetVersionChecksum' => $run->datasetVersion?->checksum,
            'proofreadVersion' => Proofread::VERSION,
            'generatedAt' => gmdate('Y-m-d H:i:s').' UTC',
            'truncate' => $this->truncator(),
        ])->render();
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
