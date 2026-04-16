<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Models;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persisted Eloquent representation of a single shadow evaluation: the result
 * of running a set of assertions against a previously-captured production
 * agent invocation (ShadowCapture).
 *
 * @property string $id
 * @property string $capture_id
 * @property string $agent_class
 * @property bool $passed
 * @property int $total_assertions
 * @property int $passed_assertions
 * @property int $failed_assertions
 * @property list<array<string, mixed>> $assertion_results
 * @property float|null $judge_cost_usd
 * @property int|null $judge_tokens_in
 * @property int|null $judge_tokens_out
 * @property float $evaluation_duration_ms
 * @property CarbonInterface $evaluated_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property ShadowCapture|null $capture
 *
 * @method static Builder<ShadowEval> forAgent(string $agentClass)
 * @method static Builder<ShadowEval> passedOnly()
 * @method static Builder<ShadowEval> failedOnly()
 * @method static Builder<ShadowEval> evaluatedBetween(DateTimeInterface $from, DateTimeInterface $to)
 */
class ShadowEval extends Model
{
    use HasUlids;

    protected $table = 'shadow_evals';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'capture_id',
        'agent_class',
        'passed',
        'total_assertions',
        'passed_assertions',
        'failed_assertions',
        'assertion_results',
        'judge_cost_usd',
        'judge_tokens_in',
        'judge_tokens_out',
        'evaluation_duration_ms',
        'evaluated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'passed' => 'boolean',
        'total_assertions' => 'integer',
        'passed_assertions' => 'integer',
        'failed_assertions' => 'integer',
        'assertion_results' => 'array',
        'judge_cost_usd' => 'float',
        'judge_tokens_in' => 'integer',
        'judge_tokens_out' => 'integer',
        'evaluation_duration_ms' => 'float',
        'evaluated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ShadowCapture, $this>
     */
    public function capture(): BelongsTo
    {
        return $this->belongsTo(ShadowCapture::class, 'capture_id');
    }

    /**
     * @param  Builder<ShadowEval>  $query
     * @return Builder<ShadowEval>
     */
    public function scopeForAgent(Builder $query, string $agentClass): Builder
    {
        return $query->where('agent_class', $agentClass);
    }

    /**
     * @param  Builder<ShadowEval>  $query
     * @return Builder<ShadowEval>
     */
    public function scopePassedOnly(Builder $query): Builder
    {
        return $query->where('passed', true);
    }

    /**
     * @param  Builder<ShadowEval>  $query
     * @return Builder<ShadowEval>
     */
    public function scopeFailedOnly(Builder $query): Builder
    {
        return $query->where('passed', false);
    }

    /**
     * @param  Builder<ShadowEval>  $query
     * @return Builder<ShadowEval>
     */
    public function scopeEvaluatedBetween(Builder $query, DateTimeInterface $from, DateTimeInterface $to): Builder
    {
        return $query
            ->where('evaluated_at', '>=', $from)
            ->where('evaluated_at', '<=', $to);
    }
}
