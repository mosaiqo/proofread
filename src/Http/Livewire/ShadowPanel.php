<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Http\Livewire;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Models\ShadowEval;

#[Layout('proofread::layout')]
class ShadowPanel extends Component
{
    use WithPagination;

    #[Url(as: 'agent')]
    public ?string $agentFilter = null;

    #[Url(as: 'status')]
    public ?string $statusFilter = null;

    #[Url(as: 'q')]
    public string $search = '';

    public ?string $selectedCaptureId = null;

    /**
     * @var array{
     *     captures_24h: int,
     *     seven_day_pass_rate: float|null,
     *     pending: int
     * }
     */
    public array $stats = [
        'captures_24h' => 0,
        'seven_day_pass_rate' => null,
        'pending' => 0,
    ];

    public function updating(string $name): void
    {
        if (in_array($name, ['agentFilter', 'statusFilter', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function selectCapture(string $id): void
    {
        $this->selectedCaptureId = $id;
    }

    public function closeCapture(): void
    {
        $this->selectedCaptureId = null;
    }

    public function render(): View
    {
        $this->stats = $this->computeStats();

        return view('proofread::shadow.panel', [
            'captures' => $this->filteredCaptures(),
            'agentOptions' => $this->availableAgents(),
            'selectedCapture' => $this->resolveSelected(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, ShadowCapture>
     */
    private function filteredCaptures(): LengthAwarePaginator
    {
        $query = ShadowCapture::query();

        if ($this->agentFilter !== null && $this->agentFilter !== '') {
            $query->where('agent_class', $this->agentFilter);
        }

        if ($this->statusFilter === 'pending') {
            $query->whereDoesntHave('evals');
        } elseif ($this->statusFilter === 'evaluated_pass') {
            $query->whereHas('evals', function ($q): void {
                $q->where('passed', true);
            });
        } elseif ($this->statusFilter === 'evaluated_fail') {
            $query->whereHas('evals', function ($q): void {
                $q->where('passed', false);
            });
        }

        if ($this->search !== '') {
            $needle = '%'.$this->search.'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('prompt_hash', 'like', $needle)
                    ->orWhere('agent_class', 'like', $needle);
            });
        }

        $query
            ->with(['evals' => function ($q): void {
                $q->orderByDesc('evaluated_at');
            }])
            ->orderByDesc('captured_at');

        return $query->paginate(25);
    }

    /**
     * @return array{
     *     captures_24h: int,
     *     seven_day_pass_rate: float|null,
     *     pending: int
     * }
     */
    private function computeStats(): array
    {
        $captures24h = ShadowCapture::query()
            ->where('captured_at', '>=', now()->subDay())
            ->count();

        $pending = ShadowCapture::query()
            ->whereDoesntHave('evals')
            ->count();

        return [
            'captures_24h' => $captures24h,
            'seven_day_pass_rate' => $this->passRateSince(now()->subDays(7)),
            'pending' => $pending,
        ];
    }

    private function passRateSince(\DateTimeInterface $since): ?float
    {
        $total = ShadowEval::query()
            ->where('evaluated_at', '>=', $since)
            ->count();

        if ($total === 0) {
            return null;
        }

        $passed = ShadowEval::query()
            ->where('evaluated_at', '>=', $since)
            ->where('passed', true)
            ->count();

        return $passed / $total;
    }

    /**
     * @return list<string>
     */
    private function availableAgents(): array
    {
        /** @var list<string> $agents */
        $agents = ShadowCapture::query()
            ->select('agent_class')
            ->distinct()
            ->orderBy('agent_class')
            ->pluck('agent_class')
            ->all();

        return $agents;
    }

    private function resolveSelected(): ?ShadowCapture
    {
        if ($this->selectedCaptureId === null) {
            return null;
        }

        /** @var ShadowCapture|null $capture */
        $capture = ShadowCapture::query()
            ->with(['evals' => function ($q): void {
                $q->orderByDesc('evaluated_at');
            }])
            ->where('id', $this->selectedCaptureId)
            ->first();

        return $capture;
    }
}
