<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string|null $suite_class
 * @property string $dataset_name
 * @property string|null $dataset_version_id
 * @property list<string> $subject_labels
 * @property string|null $commit_sha
 * @property int $total_runs
 * @property int $passed_runs
 * @property int $failed_runs
 * @property float|null $total_cost_usd
 * @property float $duration_ms
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property EvalDatasetVersion|null $datasetVersion
 * @property Collection<int, EvalRun> $runs
 */
class EvalComparison extends Model
{
    use HasUlids;

    protected $table = 'eval_comparisons';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'suite_class',
        'dataset_name',
        'dataset_version_id',
        'subject_labels',
        'commit_sha',
        'total_runs',
        'passed_runs',
        'failed_runs',
        'total_cost_usd',
        'duration_ms',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'subject_labels' => 'array',
        'total_runs' => 'integer',
        'passed_runs' => 'integer',
        'failed_runs' => 'integer',
        'total_cost_usd' => 'float',
        'duration_ms' => 'float',
    ];

    /**
     * @return BelongsTo<EvalDatasetVersion, $this>
     */
    public function datasetVersion(): BelongsTo
    {
        return $this->belongsTo(EvalDatasetVersion::class, 'dataset_version_id');
    }

    /**
     * @return HasMany<EvalRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(EvalRun::class, 'comparison_id');
    }

    /**
     * Returns the run with the highest pass rate. Falls back to the fastest
     * run as tiebreaker. Returns null when the comparison has no runs.
     */
    public function bestByPassRate(): ?EvalRun
    {
        /** @var EvalRun|null $run */
        $run = $this->runs()
            ->orderByRaw('CAST(pass_count AS REAL) / NULLIF(total_count, 0) DESC')
            ->orderBy('duration_ms', 'asc')
            ->first();

        return $run;
    }

    /**
     * Returns the run with the lowest total_cost_usd, ignoring runs where
     * the cost is null. Returns null if every run has a null cost.
     */
    public function cheapest(): ?EvalRun
    {
        /** @var EvalRun|null $run */
        $run = $this->runs()
            ->whereNotNull('total_cost_usd')
            ->orderBy('total_cost_usd', 'asc')
            ->first();

        return $run;
    }

    /**
     * Returns the run with the lowest duration. Returns null when the
     * comparison has no runs.
     */
    public function fastest(): ?EvalRun
    {
        /** @var EvalRun|null $run */
        $run = $this->runs()
            ->orderBy('duration_ms', 'asc')
            ->first();

        return $run;
    }
}
