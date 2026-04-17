<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mosaiqo\Proofread\Models\EvalComparison;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

#[Layout('proofread::layout')]
class ComparisonDetail extends Component
{
    public EvalComparison $comparison;

    public ?string $selectedCellId = null;

    public function mount(EvalComparison $comparison): void
    {
        $this->comparison = $comparison->load([
            'runs.results',
            'runs.dataset',
            'datasetVersion',
        ]);
    }

    public function selectCell(string $subjectLabel, int $caseIndex): void
    {
        $this->selectedCellId = $subjectLabel.'::'.$caseIndex;
    }

    public function closeCell(): void
    {
        $this->selectedCellId = null;
    }

    public function render(): View
    {
        return view('proofread::comparisons.detail', [
            'matrix' => $this->buildMatrix(),
            'summary' => $this->buildSummary(),
            'selectedCell' => $this->resolveSelectedCell(),
            'winners' => $this->buildWinners(),
        ]);
    }

    /**
     * @return array{
     *     subjects: list<string>,
     *     cases: list<array{case_index: int, case_name: string|null, cells: array<string, EvalResult|null>}>,
     *     aggregates: array<string, array{pass_rate: float|null, total_cost_usd: float|null, avg_latency_ms: float|null, total_tokens: int}>,
     * }
     */
    private function buildMatrix(): array
    {
        /** @var list<string> $subjects */
        $subjects = (array) $this->comparison->subject_labels;

        $runsBySubject = [];
        foreach ($this->comparison->runs as $run) {
            $label = (string) $run->subject_label;
            $runsBySubject[$label] = $run;
        }

        $caseKeys = [];
        foreach ($this->comparison->runs as $run) {
            foreach ($run->results as $result) {
                $caseKeys[$result->case_index] = $result->case_name;
            }
        }

        ksort($caseKeys);

        $cases = [];
        foreach ($caseKeys as $caseIndex => $caseName) {
            $cells = [];
            foreach ($subjects as $subjectLabel) {
                $run = $runsBySubject[$subjectLabel] ?? null;
                $result = null;
                if ($run !== null) {
                    $result = $run->results->firstWhere('case_index', $caseIndex);
                }
                $cells[$subjectLabel] = $result;
            }

            $cases[] = [
                'case_index' => (int) $caseIndex,
                'case_name' => is_string($caseName) ? $caseName : null,
                'cells' => $cells,
            ];
        }

        $aggregates = [];
        foreach ($subjects as $subjectLabel) {
            $run = $runsBySubject[$subjectLabel] ?? null;
            if ($run === null) {
                $aggregates[$subjectLabel] = [
                    'pass_rate' => null,
                    'total_cost_usd' => null,
                    'avg_latency_ms' => null,
                    'total_tokens' => 0,
                ];

                continue;
            }

            $results = $run->results;
            $latencies = $results
                ->filter(fn (EvalResult $r): bool => $r->latency_ms !== null)
                ->map(fn (EvalResult $r): float => (float) $r->latency_ms)
                ->all();
            $avgLatency = $latencies === [] ? null : array_sum($latencies) / count($latencies);

            $tokensIn = (int) ($run->total_tokens_in ?? 0);
            $tokensOut = (int) ($run->total_tokens_out ?? 0);

            $aggregates[$subjectLabel] = [
                'pass_rate' => $run->passRate(),
                'total_cost_usd' => $run->total_cost_usd,
                'avg_latency_ms' => $avgLatency,
                'total_tokens' => $tokensIn + $tokensOut,
            ];
        }

        return [
            'subjects' => $subjects,
            'cases' => $cases,
            'aggregates' => $aggregates,
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     dataset_name: string,
     *     subject_count: int,
     *     total_runs: int,
     *     passed_runs: int,
     *     failed_runs: int,
     *     all_passed: bool,
     *     total_cost_usd: float|null,
     *     duration_ms: float,
     *     commit_sha: string|null,
     *     created_at_formatted: string|null,
     *     created_at_human: string|null,
     *     dataset_version_id: string|null,
     * }
     */
    private function buildSummary(): array
    {
        $checksum = $this->comparison->datasetVersion?->checksum;

        return [
            'name' => $this->comparison->name,
            'dataset_name' => $this->comparison->dataset_name,
            'subject_count' => count((array) $this->comparison->subject_labels),
            'total_runs' => $this->comparison->total_runs,
            'passed_runs' => $this->comparison->passed_runs,
            'failed_runs' => $this->comparison->failed_runs,
            'all_passed' => $this->comparison->passed_runs === $this->comparison->total_runs,
            'total_cost_usd' => $this->comparison->total_cost_usd,
            'duration_ms' => $this->comparison->duration_ms,
            'commit_sha' => $this->comparison->commit_sha,
            'created_at_formatted' => $this->comparison->created_at?->format('Y-m-d H:i:s'),
            'created_at_human' => $this->comparison->created_at?->diffForHumans(),
            'dataset_version_id' => is_string($checksum) ? substr($checksum, 0, 12) : null,
        ];
    }

    /**
     * @return array{
     *     best_pass_rate: ?array{subject_label: string, pass_rate: float},
     *     cheapest: ?array{subject_label: string, cost_usd: float},
     *     fastest: ?array{subject_label: string, duration_ms: float},
     * }
     */
    private function buildWinners(): array
    {
        $best = $this->comparison->bestByPassRate();
        $cheap = $this->comparison->cheapest();
        $fast = $this->comparison->fastest();

        return [
            'best_pass_rate' => $best === null ? null : [
                'subject_label' => (string) $best->subject_label,
                'pass_rate' => $best->passRate(),
            ],
            'cheapest' => $cheap === null || $cheap->total_cost_usd === null ? null : [
                'subject_label' => (string) $cheap->subject_label,
                'cost_usd' => (float) $cheap->total_cost_usd,
            ],
            'fastest' => $fast === null ? null : [
                'subject_label' => (string) $fast->subject_label,
                'duration_ms' => (float) $fast->duration_ms,
            ],
        ];
    }

    /**
     * @return array{subject_label: string, run: EvalRun, result: EvalResult}|null
     */
    private function resolveSelectedCell(): ?array
    {
        if ($this->selectedCellId === null) {
            return null;
        }

        $parts = explode('::', $this->selectedCellId, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$subjectLabel, $caseIndexRaw] = $parts;
        $caseIndex = (int) $caseIndexRaw;

        $run = $this->comparison->runs->firstWhere('subject_label', $subjectLabel);
        if ($run === null) {
            return null;
        }

        $result = $run->results->firstWhere('case_index', $caseIndex);
        if ($result === null) {
            return null;
        }

        return [
            'subject_label' => $subjectLabel,
            'run' => $run,
            'result' => $result,
        ];
    }
}
