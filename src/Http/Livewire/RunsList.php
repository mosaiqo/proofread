<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Http\Livewire;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalRun;

#[Layout('proofread::layout')]
class RunsList extends Component
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
     *     last_24h_pass_rate: float|null,
     *     this_week_pass_rate: float|null,
     *     total_runs: int,
     *     total_cost_usd: float
     * }
     */
    public array $stats = [
        'last_24h_pass_rate' => null,
        'this_week_pass_rate' => null,
        'total_runs' => 0,
        'total_cost_usd' => 0.0,
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

        return view('proofread::runs.list', [
            'runs' => $this->filteredRuns(),
            'datasetOptions' => $this->availableDatasets(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, EvalRun>
     */
    private function filteredRuns(): LengthAwarePaginator
    {
        $query = EvalRun::query()->orderByDesc('created_at');

        if ($this->datasetFilter !== null && $this->datasetFilter !== '') {
            $query->where('dataset_name', $this->datasetFilter);
        }

        if ($this->statusFilter === 'passed') {
            $query->where('passed', true);
        } elseif ($this->statusFilter === 'failed') {
            $query->where('passed', false);
        }

        if ($this->search !== '') {
            $query->where('dataset_name', 'like', '%'.$this->search.'%');
        }

        return $query->paginate(20);
    }

    /**
     * @return array{
     *     last_24h_pass_rate: float|null,
     *     this_week_pass_rate: float|null,
     *     total_runs: int,
     *     total_cost_usd: float
     * }
     */
    private function computeStats(): array
    {
        return [
            'last_24h_pass_rate' => $this->passRateSince(now()->subDay()),
            'this_week_pass_rate' => $this->passRateSince(now()->subWeek()),
            'total_runs' => EvalRun::query()->count(),
            'total_cost_usd' => (float) EvalRun::query()->sum('total_cost_usd'),
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

    /**
     * @return array<int, string>
     */
    private function availableDatasets(): array
    {
        /** @var array<int, string> $names */
        $names = EvalDataset::query()->orderBy('name')->pluck('name')->all();

        return $names;
    }
}
