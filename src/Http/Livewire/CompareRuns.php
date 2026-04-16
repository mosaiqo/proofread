<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mosaiqo\Proofread\Diff\CaseDelta;
use Mosaiqo\Proofread\Diff\EvalRunDelta;
use Mosaiqo\Proofread\Diff\EvalRunDiff;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

#[Layout('proofread::layout')]
class CompareRuns extends Component
{
    #[Url(as: 'base')]
    public ?string $baseId = null;

    #[Url(as: 'head')]
    public ?string $headId = null;

    #[Url(as: 'status')]
    public ?string $statusFilter = null;

    public ?int $selectedCaseIndex = null;

    public function selectCase(int $caseIndex): void
    {
        $this->selectedCaseIndex = $caseIndex;
    }

    public function closeCase(): void
    {
        $this->selectedCaseIndex = null;
    }

    public function setStatusFilter(?string $status): void
    {
        $this->statusFilter = $status;
        $this->selectedCaseIndex = null;
    }

    public function render(): View
    {
        $base = $this->resolveRun($this->baseId);
        $head = $this->resolveRun($this->headId);

        $state = $this->classifyState($base, $head);
        $delta = null;
        $filteredCases = [];
        $counts = ['all' => 0, 'regression' => 0, 'improvement' => 0, 'stable' => 0];
        $baseCase = null;
        $headCase = null;

        if ($state === 'ok' && $base !== null && $head !== null) {
            $delta = app(EvalRunDiff::class)->compute($base, $head);
            $counts = $this->countsFor($delta);
            $filteredCases = $this->orderAndFilter($delta);

            if ($this->selectedCaseIndex !== null) {
                [$baseCase, $headCase] = $this->resolveCasePair($base, $head, $this->selectedCaseIndex);
            }
        }

        return view('proofread::compare.show', [
            'base' => $base,
            'head' => $head,
            'state' => $state,
            'delta' => $delta,
            'filteredCases' => $filteredCases,
            'counts' => $counts,
            'runOptions' => $this->availableRuns(),
            'baseCase' => $baseCase,
            'headCase' => $headCase,
        ]);
    }

    private function resolveRun(?string $id): ?EvalRun
    {
        if ($id === null || $id === '') {
            return null;
        }

        /** @var EvalRun|null $run */
        $run = EvalRun::query()->where('id', $id)->first();

        return $run;
    }

    private function classifyState(?EvalRun $base, ?EvalRun $head): string
    {
        if ($this->baseId === null && $this->headId === null) {
            return 'picker';
        }

        if ($this->baseId !== null && $base === null) {
            return 'base_missing';
        }

        if ($this->headId !== null && $head === null) {
            return 'head_missing';
        }

        if ($base !== null && $head !== null && $base->dataset_name !== $head->dataset_name) {
            return 'dataset_mismatch';
        }

        if ($base === null || $head === null) {
            return 'picker';
        }

        return 'ok';
    }

    /**
     * @return array{all: int, regression: int, improvement: int, stable: int}
     */
    private function countsFor(EvalRunDelta $delta): array
    {
        return [
            'all' => $delta->totalCases,
            'regression' => $delta->regressions,
            'improvement' => $delta->improvements,
            'stable' => $delta->stablePasses + $delta->stableFailures,
        ];
    }

    /**
     * @return list<CaseDelta>
     */
    private function orderAndFilter(EvalRunDelta $delta): array
    {
        $priority = [
            'regression' => 0,
            'improvement' => 1,
            'head_only' => 2,
            'base_only' => 3,
            'stable_fail' => 4,
            'stable_pass' => 5,
        ];

        $cases = $delta->cases;
        usort($cases, function (CaseDelta $a, CaseDelta $b) use ($priority): int {
            $pa = $priority[$a->status] ?? 99;
            $pb = $priority[$b->status] ?? 99;
            if ($pa === $pb) {
                return $a->caseIndex <=> $b->caseIndex;
            }

            return $pa <=> $pb;
        });

        if ($this->statusFilter === null || $this->statusFilter === '') {
            return $cases;
        }

        $filter = $this->statusFilter;

        return array_values(array_filter(
            $cases,
            static function (CaseDelta $case) use ($filter): bool {
                if ($filter === 'stable') {
                    return $case->status === 'stable_pass' || $case->status === 'stable_fail';
                }

                return $case->status === $filter;
            },
        ));
    }

    /**
     * @return array{0: ?EvalResult, 1: ?EvalResult}
     */
    private function resolveCasePair(EvalRun $base, EvalRun $head, int $caseIndex): array
    {
        /** @var EvalResult|null $baseCase */
        $baseCase = EvalResult::query()
            ->where('run_id', $base->id)
            ->where('case_index', $caseIndex)
            ->first();

        /** @var EvalResult|null $headCase */
        $headCase = EvalResult::query()
            ->where('run_id', $head->id)
            ->where('case_index', $caseIndex)
            ->first();

        return [$baseCase, $headCase];
    }

    /**
     * @return array<string, list<array{id: string, label: string}>>
     */
    private function availableRuns(): array
    {
        /** @var Collection<int, EvalRun> $runs */
        $runs = EvalRun::query()->orderByDesc('created_at')->limit(100)->get();

        $out = [];
        foreach ($runs as $run) {
            $datasetName = $run->dataset_name;
            $out[$datasetName] ??= [];
            $createdAt = $run->created_at?->format('Y-m-d H:i') ?? 'unknown';
            $short = substr($run->id, -8);
            $out[$datasetName][] = [
                'id' => $run->id,
                'label' => sprintf('%s (%s, %s)', $short, $createdAt, $run->passed ? 'passed' : 'failed'),
            ];
        }

        return $out;
    }
}
