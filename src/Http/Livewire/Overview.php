<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Http\Livewire;

use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalRun;

#[Layout('proofread::layout')]
class Overview extends Component
{
    /**
     * @var array{
     *     total_runs: int,
     *     total_cost_usd: float,
     *     seven_day_pass_rate: float|null,
     *     active_datasets: int
     * }
     */
    public array $globalStats = [
        'total_runs' => 0,
        'total_cost_usd' => 0.0,
        'seven_day_pass_rate' => null,
        'active_datasets' => 0,
    ];

    public function render(): View
    {
        $this->globalStats = $this->computeGlobalStats();

        return view('proofread::overview.show', [
            'globalStats' => $this->globalStats,
            'passRateTrend' => $this->computePassRateTrend(),
            'topFailingDatasets' => $this->computeTopFailingDatasets(),
            'recentRegressions' => $this->computeRecentRegressions(),
            'recentRuns' => $this->loadRecentRuns(),
        ]);
    }

    /**
     * @return array{
     *     total_runs: int,
     *     total_cost_usd: float,
     *     seven_day_pass_rate: float|null,
     *     active_datasets: int
     * }
     */
    private function computeGlobalStats(): array
    {
        return [
            'total_runs' => EvalRun::query()->count(),
            'total_cost_usd' => (float) EvalRun::query()->sum('total_cost_usd'),
            'seven_day_pass_rate' => $this->passRateSince(now()->subDays(7)),
            'active_datasets' => $this->activeDatasetsCount(),
        ];
    }

    private function passRateSince(\DateTimeInterface $since): ?float
    {
        $total = EvalRun::query()->where('created_at', '>=', $since)->count();

        if ($total === 0) {
            return null;
        }

        $passed = EvalRun::query()
            ->where('created_at', '>=', $since)
            ->where('passed', true)
            ->count();

        return $passed / $total;
    }

    private function activeDatasetsCount(): int
    {
        $since = now()->subDays(30);

        return EvalDataset::query()
            ->whereHas('runs', function ($query) use ($since): void {
                $query->where('created_at', '>=', $since);
            })
            ->count();
    }

    /**
     * Returns a 30-element array keyed by Y-m-d (oldest first) with daily
     * pass rate in [0..1], or null where no runs happened that day.
     *
     * @return array<string, float|null>
     */
    private function computePassRateTrend(): array
    {
        $since = now()->subDays(30)->startOfDay();

        /** @var array<string, array{passed: int, total: int}> $raw */
        $raw = [];

        /** @var Collection<int, EvalRun> $runs */
        $runs = EvalRun::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, SUM(pass_count) as passed, SUM(total_count) as total')
            ->groupBy('day')
            ->get();

        foreach ($runs as $row) {
            /** @var string $day */
            $day = $row->getAttribute('day');
            /** @var int|string $passed */
            $passed = $row->getAttribute('passed') ?? 0;
            /** @var int|string $total */
            $total = $row->getAttribute('total') ?? 0;
            $raw[$day] = ['passed' => (int) $passed, 'total' => (int) $total];
        }

        $out = [];
        for ($i = 29; $i >= 0; $i--) {
            $key = now()->subDays($i)->format('Y-m-d');
            if (isset($raw[$key]) && $raw[$key]['total'] > 0) {
                $out[$key] = $raw[$key]['passed'] / $raw[$key]['total'];
            } else {
                $out[$key] = null;
            }
        }

        return $out;
    }

    /**
     * @return list<array{name: string, fail_count: int, total_count: int, fail_rate: float}>
     */
    private function computeTopFailingDatasets(): array
    {
        $since = now()->subDays(7);

        /** @var Collection<int, EvalRun> $rows */
        $rows = EvalRun::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('dataset_name, SUM(fail_count) as fail_total, SUM(total_count) as total_total')
            ->groupBy('dataset_name')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            /** @var int|string $failTotal */
            $failTotal = $row->getAttribute('fail_total') ?? 0;
            /** @var int|string $totalTotal */
            $totalTotal = $row->getAttribute('total_total') ?? 0;
            $failCount = (int) $failTotal;
            $totalCount = (int) $totalTotal;

            if ($failCount === 0) {
                continue;
            }

            $out[] = [
                'name' => (string) $row->dataset_name,
                'fail_count' => $failCount,
                'total_count' => $totalCount,
                'fail_rate' => $totalCount > 0 ? $failCount / $totalCount : 0.0,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['fail_count'] <=> $a['fail_count']);

        return array_slice($out, 0, 5);
    }

    /**
     * Detects recent regressions by looking at the last ~20 runs and,
     * per dataset, checking whether the most recent run has a lower
     * pass rate than the one before it.
     *
     * @return list<array{
     *     dataset_name: string,
     *     base_id: string,
     *     head_id: string,
     *     base_pass_rate: float,
     *     head_pass_rate: float,
     *     delta: float,
     *     head_created_at: CarbonInterface|null
     * }>
     */
    private function computeRecentRegressions(): array
    {
        /** @var Collection<int, EvalRun> $recent */
        $recent = EvalRun::query()
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        /** @var array<string, list<EvalRun>> $byDataset */
        $byDataset = [];
        foreach ($recent as $run) {
            $byDataset[$run->dataset_name] ??= [];
            if (count($byDataset[$run->dataset_name]) < 2) {
                $byDataset[$run->dataset_name][] = $run;
            }
        }

        $regressions = [];
        foreach ($byDataset as $datasetName => $runs) {
            if (count($runs) < 2) {
                continue;
            }

            [$head, $base] = $runs;
            $headRate = $head->passRate();
            $baseRate = $base->passRate();

            if ($headRate >= $baseRate) {
                continue;
            }

            $regressions[] = [
                'dataset_name' => $datasetName,
                'base_id' => $base->id,
                'head_id' => $head->id,
                'base_pass_rate' => $baseRate,
                'head_pass_rate' => $headRate,
                'delta' => $headRate - $baseRate,
                'head_created_at' => $head->created_at,
            ];
        }

        usort(
            $regressions,
            static function (array $a, array $b): int {
                $aTs = $a['head_created_at']?->getTimestamp() ?? 0;
                $bTs = $b['head_created_at']?->getTimestamp() ?? 0;

                return $bTs <=> $aTs;
            },
        );

        return array_slice($regressions, 0, 5);
    }

    /**
     * @return Collection<int, EvalRun>
     */
    private function loadRecentRuns(): Collection
    {
        /** @var Collection<int, EvalRun> $runs */
        $runs = EvalRun::query()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return $runs;
    }
}
