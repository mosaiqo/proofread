<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $name
 * @property int $case_count
 * @property string|null $checksum
 * @property EvalDatasetVersion|null $latestVersion
 */
class EvalDataset extends Model
{
    use HasUlids;

    protected $table = 'eval_datasets';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'case_count',
        'checksum',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'case_count' => 'integer',
    ];

    /**
     * @return HasMany<EvalRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(EvalRun::class, 'dataset_id');
    }

    /**
     * @return HasMany<EvalDatasetVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(EvalDatasetVersion::class, 'eval_dataset_id');
    }

    /**
     * @return HasOne<EvalDatasetVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(EvalDatasetVersion::class, 'eval_dataset_id')
            ->latestOfMany('first_seen_at');
    }
}
