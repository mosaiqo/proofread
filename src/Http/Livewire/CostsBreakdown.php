<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Http\Livewire;

use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mosaiqo\Proofread\Models\EvalRun;

#[Layout('proofread::layout')]
class CostsBreakdown extends Component
{
    #[Url(as: 'window')]
    public string $windowFilter = '30d';

    public function render(): View
    {
        $windowStart = $this->resolveWindowStart();

        return view('proofread::costs.show', [
            'windowFilter' => $this->windowFilter,
            'windowStart' => $windowStart,
            'windowCounts' => $this->windowCounts(),
            'totalCost' => $this->computeTotalCost($windowStart),
            'totalRuns' => $this->computeTotalRuns($windowStart),
            'avgCostPerRun' => $this->computeAvgCostPerRun($windowStart),
            'mostExpensiveModel' => $this->computeMostExpensiveModel($windowStart),
            'byModel' => $this->breakdownByModel($windowStart),
            'byDataset' => $this->breakdownByDataset($windowStart),
            'dailyTrend' => $this->dailyCostTrend($windowStart),
        ]);
    }

    private function resolveWindowStart(): ?CarbonInterface
    {
        return match ($this->windowFilter) {
            '7d' => now()->subDays(7),
            'all' => null,
            default => now()->subDays(30),
        };
    }

    /**
     * @return array{"7d": int, "30d": int, "all": int}
     */
    private function windowCounts(): array
    {
        return [
            '7d' => EvalRun::query()->where('created_at', '>=', now()->subDays(7))->count(),
            '30d' => EvalRun::query()->where('created_at', '>=', now()->subDays(30))->count(),
            'all' => EvalRun::query()->count(),
        ];
    }

    private function computeTotalCost(?CarbonInterface $since): float
    {
        $query = EvalRun::query()->whereNotNull('total_cost_usd');

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        return (float) $query->sum('total_cost_usd');
    }

    private function computeTotalRuns(?CarbonInterface $since): int
    {
        $query = EvalRun::query();

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        return $query->count();
    }

    private function computeAvgCostPerRun(?CarbonInterface $since): ?float
    {
        $query = EvalRun::query()->whereNotNull('total_cost_usd');

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        $avg = $query->avg('total_cost_usd');

        return $avg === null ? null : (float) $avg;
    }

    private function computeMostExpensiveModel(?CarbonInterface $since): ?string
    {
        $query = EvalRun::query()
            ->whereNotNull('model')
            ->whereNotNull('total_cost_usd')
            ->selectRaw('model, SUM(total_cost_usd) as total_cost')
            ->groupBy('model')
            ->orderByDesc('total_cost')
            ->limit(1);

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        /** @var EvalRun|null $row */
        $row = $query->first();

        if ($row === null) {
            return null;
        }

        return (string) $row->getAttribute('model');
    }

    /**
     * @return list<array{
     *     model: string,
     *     runs: int,
     *     total_tokens: int,
     *     total_cost: float,
     *     avg_cost: float,
     *     percentage: float,
     * }>
     */
    private function breakdownByModel(?CarbonInterface $since): array
    {
        $query = EvalRun::query()
            ->whereNotNull('model')
            ->whereNotNull('total_cost_usd')
            ->selectRaw(
                'model, '
                .'COUNT(*) as runs, '
                .'COALESCE(SUM(total_tokens_in), 0) as tokens_in, '
                .'COALESCE(SUM(total_tokens_out), 0) as tokens_out, '
                .'SUM(total_cost_usd) as total_cost, '
                .'AVG(total_cost_usd) as avg_cost'
            )
            ->groupBy('model')
            ->orderByDesc('total_cost');

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        /** @var Collection<int, EvalRun> $rows */
        $rows = $query->get();

        $grandTotal = 0.0;
        foreach ($rows as $row) {
            $grandTotal += (float) $row->getAttribute('total_cost');
        }

        $out = [];
        foreach ($rows as $row) {
            $totalCost = (float) $row->getAttribute('total_cost');
            $tokensIn = (int) $row->getAttribute('tokens_in');
            $tokensOut = (int) $row->getAttribute('tokens_out');

            $out[] = [
                'model' => (string) $row->getAttribute('model'),
                'runs' => (int) $row->getAttribute('runs'),
                'total_tokens' => $tokensIn + $tokensOut,
                'total_cost' => $totalCost,
                'avg_cost' => (float) $row->getAttribute('avg_cost'),
                'percentage' => $grandTotal > 0.0 ? $totalCost / $grandTotal : 0.0,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{
     *     dataset_name: string,
     *     runs: int,
     *     total_cost: float,
     *     avg_cost: float,
     * }>
     */
    private function breakdownByDataset(?CarbonInterface $since): array
    {
        $query = EvalRun::query()
            ->whereNotNull('total_cost_usd')
            ->selectRaw(
                'dataset_name, '
                .'COUNT(*) as runs, '
                .'SUM(total_cost_usd) as total_cost, '
                .'AVG(total_cost_usd) as avg_cost'
            )
            ->groupBy('dataset_name')
            ->orderByDesc('total_cost');

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        /** @var Collection<int, EvalRun> $rows */
        $rows = $query->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'dataset_name' => (string) $row->getAttribute('dataset_name'),
                'runs' => (int) $row->getAttribute('runs'),
                'total_cost' => (float) $row->getAttribute('total_cost'),
                'avg_cost' => (float) $row->getAttribute('avg_cost'),
            ];
        }

        return $out;
    }

    /**
     * Returns an ordered array keyed by Y-m-d with daily cost (or 0 when no
     * runs happened that day). Window length matches the active filter; the
     * "all" mode shows the last 30 days.
     *
     * @return array<string, float>
     */
    private function dailyCostTrend(?CarbonInterface $since): array
    {
        $days = $this->trendDayCount();
        $start = now()->subDays($days - 1)->startOfDay();

        /** @var array<string, float> $raw */
        $raw = [];

        /** @var Collection<int, EvalRun> $rows */
        $rows = EvalRun::query()
            ->where('created_at', '>=', $start)
            ->whereNotNull('total_cost_usd')
            ->selectRaw('DATE(created_at) as day, SUM(total_cost_usd) as cost')
            ->groupBy('day')
            ->get();

        foreach ($rows as $row) {
            $day = (string) $row->getAttribute('day');
            $raw[$day] = (float) $row->getAttribute('cost');
        }

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $key = now()->subDays($i)->format('Y-m-d');
            $out[$key] = $raw[$key] ?? 0.0;
        }

        return $out;
    }

    private function trendDayCount(): int
    {
        return match ($this->windowFilter) {
            '7d' => 7,
            default => 30,
        };
    }
}
