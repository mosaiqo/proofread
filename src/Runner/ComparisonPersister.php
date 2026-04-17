<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Runner;

use Illuminate\Support\Facades\DB;
use Mosaiqo\Proofread\Models\EvalComparison as EvalComparisonModel;
use Mosaiqo\Proofread\Models\EvalRun as EvalRunModel;
use Mosaiqo\Proofread\Support\EvalComparison;

final class ComparisonPersister
{
    public function __construct(
        private readonly EvalPersister $runPersister,
    ) {}

    public function persist(
        EvalComparison $comparison,
        ?string $suiteClass = null,
        ?string $commitSha = null,
    ): EvalComparisonModel {
        return DB::transaction(function () use ($comparison, $suiteClass, $commitSha): EvalComparisonModel {
            $labels = $comparison->subjectLabels();
            $totalRuns = count($labels);

            $model = new EvalComparisonModel;
            $model->fill([
                'name' => $comparison->name,
                'suite_class' => $suiteClass,
                'dataset_name' => $comparison->dataset->name,
                'commit_sha' => $commitSha,
                'subject_labels' => $labels,
                'total_runs' => $totalRuns,
                'passed_runs' => 0,
                'failed_runs' => 0,
                'total_cost_usd' => null,
                'duration_ms' => $comparison->durationMs,
            ]);
            $model->save();

            $passedRuns = 0;
            $failedRuns = 0;
            $totalCost = null;
            $datasetVersionId = null;

            foreach ($comparison->runs as $label => $supportRun) {
                $runModel = $this->runPersister->persist(
                    $supportRun,
                    suiteClass: $suiteClass,
                    commitSha: $commitSha,
                    comparisonId: $model->id,
                    subjectLabel: $label,
                );

                if ($runModel->passed) {
                    $passedRuns++;
                } else {
                    $failedRuns++;
                }

                if ($runModel->total_cost_usd !== null) {
                    $totalCost = ($totalCost ?? 0.0) + (float) $runModel->total_cost_usd;
                }

                if ($datasetVersionId === null && $runModel->dataset_version_id !== null) {
                    $datasetVersionId = $runModel->dataset_version_id;
                }
            }

            $model->passed_runs = $passedRuns;
            $model->failed_runs = $failedRuns;
            $model->total_cost_usd = $totalCost;
            $model->dataset_version_id = $datasetVersionId;
            $model->save();

            $model->setRelation(
                'runs',
                EvalRunModel::query()->where('comparison_id', $model->id)->get(),
            );

            return $model;
        });
    }
}
