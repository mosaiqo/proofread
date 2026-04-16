<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Models;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persisted Eloquent representation of a shadow capture: a real production
 * agent invocation (sampled + sanitized) stored for asynchronous evaluation.
 *
 * @property string $id
 * @property string $agent_class
 * @property string $prompt_hash
 * @property array<string, mixed> $input_payload
 * @property string|null $output
 * @property int|null $tokens_in
 * @property int|null $tokens_out
 * @property float|null $cost_usd
 * @property float|null $latency_ms
 * @property string|null $model_used
 * @property CarbonInterface $captured_at
 * @property float $sample_rate
 * @property bool $is_anonymized
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property Collection<int, ShadowEval> $evals
 *
 * @method static Builder<ShadowCapture> forAgent(string $agentClass)
 * @method static Builder<ShadowCapture> capturedBetween(DateTimeInterface $from, DateTimeInterface $to)
 */
class ShadowCapture extends Model
{
    use HasUlids;

    protected $table = 'shadow_captures';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'agent_class',
        'prompt_hash',
        'input_payload',
        'output',
        'tokens_in',
        'tokens_out',
        'cost_usd',
        'latency_ms',
        'model_used',
        'captured_at',
        'sample_rate',
        'is_anonymized',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'input_payload' => 'array',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'cost_usd' => 'float',
        'latency_ms' => 'float',
        'captured_at' => 'datetime',
        'sample_rate' => 'float',
        'is_anonymized' => 'boolean',
    ];

    /**
     * @return HasMany<ShadowEval, $this>
     */
    public function evals(): HasMany
    {
        return $this->hasMany(ShadowEval::class, 'capture_id');
    }

    /**
     * @param  Builder<ShadowCapture>  $query
     * @return Builder<ShadowCapture>
     */
    public function scopeForAgent(Builder $query, string $agentClass): Builder
    {
        return $query->where('agent_class', $agentClass);
    }

    /**
     * @param  Builder<ShadowCapture>  $query
     * @return Builder<ShadowCapture>
     */
    public function scopeCapturedBetween(Builder $query, DateTimeInterface $from, DateTimeInterface $to): Builder
    {
        return $query
            ->where('captured_at', '>=', $from)
            ->where('captured_at', '<=', $to);
    }
}
