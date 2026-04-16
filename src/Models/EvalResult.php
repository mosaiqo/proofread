<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persisted Eloquent representation of a single case result within a run.
 *
 * Not to be confused with the in-memory value object
 * Mosaiqo\Proofread\Support\EvalResult.
 *
 * @property string $id
 * @property string $run_id
 * @property int $case_index
 * @property string|null $case_name
 * @property array<string, mixed> $input
 * @property string|null $output
 * @property array<string, mixed>|null $expected
 * @property bool $passed
 * @property list<array<string, mixed>> $assertion_results
 * @property string|null $error_class
 * @property string|null $error_message
 * @property string|null $error_trace
 * @property float $duration_ms
 * @property float|null $latency_ms
 * @property int|null $tokens_in
 * @property int|null $tokens_out
 * @property float|null $cost_usd
 * @property string|null $model
 * @property EvalRun|null $run
 */
class EvalResult extends Model
{
    use HasUlids;

    protected $table = 'eval_results';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'run_id',
        'case_index',
        'case_name',
        'input',
        'output',
        'expected',
        'passed',
        'assertion_results',
        'error_class',
        'error_message',
        'error_trace',
        'duration_ms',
        'latency_ms',
        'tokens_in',
        'tokens_out',
        'cost_usd',
        'model',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'case_index' => 'integer',
        'input' => 'array',
        'expected' => 'array',
        'passed' => 'boolean',
        'assertion_results' => 'array',
        'duration_ms' => 'float',
        'latency_ms' => 'float',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'cost_usd' => 'float',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EvalRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(EvalRun::class, 'run_id');
    }
}
