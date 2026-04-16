<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property int $case_count
 * @property string|null $checksum
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
}
