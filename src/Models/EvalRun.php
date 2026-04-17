<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persisted Eloquent representation of an eval run.
 *
 * Not to be confused with the in-memory value object
 * Mosaiqo\Proofread\Support\EvalRun, which is what the runner returns.
 *
 * @property string $id
 * @property string $dataset_id
 * @property string|null $dataset_version_id
 * @property string $dataset_name
 * @property string|null $suite_class
 * @property string $subject_type
 * @property string|null $subject_class
 * @property string|null $commit_sha
 * @property string|null $model
 * @property bool $passed
 * @property int $pass_count
 * @property int $fail_count
 * @property int $error_count
 * @property int $total_count
 * @property float $duration_ms
 * @property float|null $total_cost_usd
 * @property int|null $total_tokens_in
 * @property int|null $total_tokens_out
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property EvalDataset|null $dataset
 * @property EvalDatasetVersion|null $datasetVersion
 * @property Collection<int, EvalResult> $results
 */
class EvalRun extends Model
{
    use HasUlids;

    protected $table = 'eval_runs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dataset_id',
        'dataset_version_id',
        'dataset_name',
        'suite_class',
        'subject_type',
        'subject_class',
        'commit_sha',
        'model',
        'passed',
        'pass_count',
        'fail_count',
        'error_count',
        'total_count',
        'duration_ms',
        'total_cost_usd',
        'total_tokens_in',
        'total_tokens_out',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'passed' => 'boolean',
        'pass_count' => 'integer',
        'fail_count' => 'integer',
        'error_count' => 'integer',
        'total_count' => 'integer',
        'duration_ms' => 'float',
        'total_cost_usd' => 'float',
        'total_tokens_in' => 'integer',
        'total_tokens_out' => 'integer',
    ];

    /**
     * @return BelongsTo<EvalDataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(EvalDataset::class, 'dataset_id');
    }

    /**
     * @return BelongsTo<EvalDatasetVersion, $this>
     */
    public function datasetVersion(): BelongsTo
    {
        return $this->belongsTo(EvalDatasetVersion::class, 'dataset_version_id');
    }

    /**
     * @return HasMany<EvalResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(EvalResult::class, 'run_id');
    }

    /**
     * @return Builder<EvalResult>
     */
    public function failures(): Builder
    {
        return EvalResult::query()
            ->where('run_id', $this->id)
            ->where('passed', false);
    }

    public function passRate(): float
    {
        if ($this->total_count === 0) {
            return 1.0;
        }

        return $this->pass_count / $this->total_count;
    }
}
