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
class DatasetsList extends Component
{
    public function render(): View
    {
        return view('proofread::datasets.list', [
            'datasets' => $this->loadDatasets(),
        ]);
    }

    /**
     * @return list<array{
     *     model: EvalDataset,
     *     name: string,
     *     case_count: int,
     *     runs_count: int,
     *     last_run_at: CarbonInterface|null,
     *     avg_cost: float|null,
     *     avg_duration: float|null,
     *     pass_history: array<int, float|null>,
     * }>
     */
    private function loadDatasets(): array
    {
        /** @var Collection<int, EvalDataset> $datasets */
        $datasets = EvalDataset::query()
            ->withCount('runs')
            ->orderBy('name')
            ->get();

        $out = [];
        foreach ($datasets as $dataset) {
            /** @var int $runsCount */
            $runsCount = $dataset->getAttribute('runs_count') ?? 0;

            $out[] = [
                'model' => $dataset,
                'name' => $dataset->name,
                'case_count' => $dataset->case_count,
                'runs_count' => $runsCount,
                'last_run_at' => $this->lastRunAt($dataset),
                'avg_cost' => $this->avgCost($dataset),
                'avg_duration' => $this->avgDuration($dataset),
                'pass_history' => $this->passHistory($dataset),
            ];
        }

        return $out;
    }

    private function lastRunAt(EvalDataset $dataset): ?CarbonInterface
    {
        /** @var EvalRun|null $latest */
        $latest = $dataset->runs()->latest()->first();

        return $latest?->created_at;
    }

    private function avgCost(EvalDataset $dataset): ?float
    {
        $avg = $dataset->runs()->whereNotNull('total_cost_usd')->avg('total_cost_usd');

        return $avg === null ? null : (float) $avg;
    }

    private function avgDuration(EvalDataset $dataset): ?float
    {
        $avg = $dataset->runs()->avg('duration_ms');

        return $avg === null ? null : (float) $avg;
    }

    /**
     * Returns a 30-element array (oldest first) with daily pass rate in [0..1],
     * or null where no runs happened that day.
     *
     * @return array<int, float|null>
     */
    private function passHistory(EvalDataset $dataset): array
    {
        $since = now()->subDays(30)->startOfDay();

        /** @var array<string, float> $raw */
        $raw = EvalRun::query()
            ->where('dataset_id', $dataset->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, AVG(CASE WHEN passed THEN 1.0 ELSE 0.0 END) as pass_rate')
            ->groupBy('day')
            ->pluck('pass_rate', 'day')
            ->map(fn ($v): float => (float) $v)
            ->all();

        $days = [];
        for ($i = 29; $i >= 0; $i--) {
            $key = now()->subDays($i)->format('Y-m-d');
            $days[] = $raw[$key] ?? null;
        }

        return $days;
    }
}
