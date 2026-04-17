<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $eval_dataset_id
 * @property string $checksum
 * @property list<array<string, mixed>> $cases
 * @property int $case_count
 * @property CarbonInterface $first_seen_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property EvalDataset|null $dataset
 */
class EvalDatasetVersion extends Model
{
    use HasUlids;

    protected $table = 'eval_dataset_versions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'eval_dataset_id',
        'checksum',
        'cases',
        'case_count',
        'first_seen_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'cases' => 'array',
        'case_count' => 'integer',
        'first_seen_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EvalDataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(EvalDataset::class, 'eval_dataset_id');
    }

    /**
     * @return HasMany<EvalRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(EvalRun::class, 'dataset_version_id');
    }
}
