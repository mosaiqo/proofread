<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Export;

use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;
use Mosaiqo\Proofread\Models\EvalComparison;
use Mosaiqo\Proofread\Proofread;

final class EvalComparisonExporter
{
    public function __construct(
        private readonly ViewFactory $views,
    ) {}

    public function render(EvalComparison $comparison, string $format): string
    {
        if ($format !== 'md' && $format !== 'html') {
            throw new InvalidArgumentException(sprintf(
                'Unsupported export format "%s"; expected "md" or "html".',
                $format,
            ));
        }

        $comparison->loadMissing(['datasetVersion', 'runs.results']);

        $matrixRows = $this->buildMatrixRows($comparison);

        $view = $format === 'html' ? 'proofread::exports.comparison.html' : 'proofread::exports.comparison.md';

        return $this->views->make($view, [
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
}
