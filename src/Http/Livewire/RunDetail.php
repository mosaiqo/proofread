<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

#[Layout('proofread::layout')]
class RunDetail extends Component
{
    public EvalRun $run;

    public ?string $selectedCaseId = null;

    public bool $onlyFailures = false;

    public function mount(EvalRun $run): void
    {
        $this->run = $run->load('dataset');
    }

    public function selectCase(string $caseId): void
    {
        $this->selectedCaseId = $caseId;
    }

    public function closeCase(): void
    {
        $this->selectedCaseId = null;
    }

    public function toggleFailures(): void
    {
        $this->onlyFailures = ! $this->onlyFailures;
    }

    public function render(): View
    {
        return view('proofread::runs.detail', [
            'cases' => $this->filteredCases(),
            'selectedCase' => $this->selectedCase(),
            'summary' => $this->buildSummary(),
        ]);
    }

    /**
     * @return Collection<int, EvalResult>
     */
    private function filteredCases(): Collection
    {
        $query = EvalResult::query()
            ->where('run_id', $this->run->id)
            ->orderBy('case_index');

        if ($this->onlyFailures) {
            $query->where('passed', false);
        }

        /** @var Collection<int, EvalResult> $cases */
        $cases = $query->get();

        return $cases;
    }

    private function selectedCase(): ?EvalResult
    {
        if ($this->selectedCaseId === null) {
            return null;
        }

        /** @var EvalResult|null $case */
        $case = EvalResult::query()
            ->where('run_id', $this->run->id)
            ->where('id', $this->selectedCaseId)
            ->first();

        return $case;
    }

    /**
     * @return array{
     *     passed: int,
     *     failed: int,
     *     errors: int,
     *     total: int,
     *     duration_ms: float,
     *     total_cost_usd: float|null,
     *     pass_rate: float,
     * }
     */
    private function buildSummary(): array
    {
        return [
            'passed' => $this->run->pass_count,
            'failed' => $this->run->fail_count,
            'errors' => $this->run->error_count,
            'total' => $this->run->total_count,
            'duration_ms' => $this->run->duration_ms,
            'total_cost_usd' => $this->run->total_cost_usd,
            'pass_rate' => $this->run->passRate(),
        ];
    }
}
