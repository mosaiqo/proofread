<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Support;

use Mosaiqo\Proofread\Models\EvalComparison;

/**
 * Resolve a textual reference into a persisted EvalComparison model.
 *
 * Accepted reference forms:
 *   - Full ULID (26 chars) — matched exactly against the comparison id.
 *   - Commit SHA prefix (4-40 hex chars) — matched via prefix against
 *     the commit_sha column, most recent first.
 *   - Literal "latest" — the most recently created comparison in the
 *     database.
 */
final class ComparisonResolver
{
    public function resolve(string $identifier): ?EvalComparison
    {
        if ($identifier === '') {
            return null;
        }

        if ($identifier === 'latest') {
            return $this->latest();
        }

        if ($this->looksLikeUlid($identifier)) {
            /** @var EvalComparison|null $comparison */
            $comparison = EvalComparison::query()->where('id', $identifier)->first();
            if ($comparison !== null) {
                return $comparison;
            }
        }

        if ($this->looksLikeCommitSha($identifier)) {
            /** @var EvalComparison|null $comparison */
            $comparison = EvalComparison::query()
                ->where('commit_sha', 'like', $identifier.'%')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();
            if ($comparison !== null) {
                return $comparison;
            }
        }

        return null;
    }

    private function latest(): ?EvalComparison
    {
        /** @var EvalComparison|null $comparison */
        $comparison = EvalComparison::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $comparison;
    }

    private function looksLikeUlid(string $ref): bool
    {
        return strlen($ref) === 26
            && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $ref) === 1;
    }

    private function looksLikeCommitSha(string $ref): bool
    {
        $len = strlen($ref);

        return $len >= 4 && $len <= 40 && preg_match('/^[0-9a-f]+$/i', $ref) === 1;
    }
}
