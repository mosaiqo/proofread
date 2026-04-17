<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Http\Livewire;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mosaiqo\Proofread\Models\EvalComparison;

#[Layout('proofread::layout')]
class ComparisonsList extends Component
{
    use WithPagination;

    #[Url(as: 'dataset')]
    public ?string $datasetFilter = null;

    #[Url(as: 'status')]
    public ?string $statusFilter = null;

    #[Url(as: 'q')]
    public string $search = '';

    /**
     * @var array{
     *     total: int,
     *     seven_day_pass_rate: float|null,
     *     active_datasets: int
     * }
     */
    public array $stats = [
        'total' => 0,
        'seven_day_pass_rate' => null,
        'active_datasets' => 0,
    ];

    public function updating(string $name): void
    {
        if (in_array($name, ['datasetFilter', 'statusFilter', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $this->stats = $this->computeStats();

        return view('proofread::comparisons.list', [
            'comparisons' => $this->filteredComparisons(),
            'stats' => $this->stats,
            'datasetOptions' => $this->availableDatasets(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, EvalComparison>
     */
    private function filteredComparisons(): LengthAwarePaginator
    {
        $query = EvalComparison::query()->orderByDesc('created_at');

        if ($this->datasetFilter !== null && $this->datasetFilter !== '') {
            $query->where('dataset_name', $this->datasetFilter);
        }

        if ($this->statusFilter === 'passed') {
            $query->whereColumn('passed_runs', '=', 'total_runs');
        } elseif ($this->statusFilter === 'failed') {
            $query->whereColumn('passed_runs', '<', 'total_runs');
        }

        if ($this->search !== '') {
            $like = '%'.$this->search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('dataset_name', 'like', $like);
            });
        }

        return $query->paginate(20);
    }

    /**
     * @return array{
     *     total: int,
     *     seven_day_pass_rate: float|null,
     *     active_datasets: int
     * }
     */
    private function computeStats(): array
    {
        return [
            'total' => EvalComparison::query()->count(),
            'seven_day_pass_rate' => $this->passRateSince(now()->subDays(7)),
            'active_datasets' => $this->activeDatasetsCount(),
        ];
    }

    private function passRateSince(\DateTimeInterface $since): ?float
    {
        $total = EvalComparison::query()->where('created_at', '>=', $since)->count();

        if ($total === 0) {
            return null;
        }

        $allPassed = EvalComparison::query()
            ->where('created_at', '>=', $since)
            ->whereColumn('passed_runs', '=', 'total_runs')
            ->count();

        return $allPassed / $total;
    }

    private function activeDatasetsCount(): int
    {
        $since = now()->subDays(30);

        return EvalComparison::query()
            ->where('created_at', '>=', $since)
            ->distinct()
            ->count('dataset_name');
    }

    /**
     * @return array<int, string>
     */
    private function availableDatasets(): array
    {
        /** @var array<int, string> $names */
        $names = EvalComparison::query()
            ->orderBy('dataset_name')
            ->distinct()
            ->pluck('dataset_name')
            ->all();

        return $names;
    }
}
