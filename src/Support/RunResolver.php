<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Support;

use Mosaiqo\Proofread\Models\EvalRun;

/**
 * Resolve a textual reference into a persisted EvalRun model.
 *
 * Accepted reference forms:
 *   - Full ULID (26 chars) — matched exactly against the run id.
 *   - Commit SHA prefix (4-40 hex chars) — matched via prefix against
 *     the commit_sha column, most recent first.
 *   - Literal "latest" — the most recently created run in the database.
 */
final class RunResolver
{
    public function resolve(string $identifier): ?EvalRun
    {
        if ($identifier === '') {
            return null;
        }

        if ($identifier === 'latest') {
            return $this->latest();
        }

        if ($this->looksLikeUlid($identifier)) {
            /** @var EvalRun|null $run */
            $run = EvalRun::query()->where('id', $identifier)->first();
            if ($run !== null) {
                return $run;
            }
        }

        if ($this->looksLikeCommitSha($identifier)) {
            /** @var EvalRun|null $run */
            $run = EvalRun::query()
                ->where('commit_sha', 'like', $identifier.'%')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();
            if ($run !== null) {
                return $run;
            }
        }

        return null;
    }

    private function latest(): ?EvalRun
    {
        /** @var EvalRun|null $run */
        $run = EvalRun::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $run;
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
