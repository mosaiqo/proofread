<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Support;

use Illuminate\Console\Command;
use Mosaiqo\Proofread\Judge\JudgeAgent;
use RuntimeException;

/**
 * Shared helper for CLI commands that accept the `--fake-judge` option.
 *
 * Accepted specs:
 * - `pass`  — every Rubric assertion auto-passes.
 * - `fail`  — every Rubric assertion auto-fails.
 * - a file path containing a JSON array of objects; each invocation consumes
 *   the next entry (scripted judge responses, in order).
 */
final class JudgeFaker
{
    /**
     * Installs a fake JudgeAgent responder based on the given spec and
     * writes a confirmation line to the command. Returns false and emits
     * an error when the spec is invalid, leaving the real judge in place.
     */
    public static function apply(Command $command, string $spec): bool
    {
        if ($spec === 'pass') {
            JudgeAgent::fake(static fn (): string => (string) json_encode([
                'passed' => true,
                'score' => 1.0,
                'reason' => 'Auto-passed by --fake-judge',
            ]));
            $command->line(sprintf(
                'Using --fake-judge=%s — all Rubric assertions will auto-pass.',
                $spec,
            ));

            return true;
        }

        if ($spec === 'fail') {
            JudgeAgent::fake(static fn (): string => (string) json_encode([
                'passed' => false,
                'score' => 0.1,
                'reason' => 'Auto-failed by --fake-judge',
            ]));
            $command->line(sprintf(
                'Using --fake-judge=%s — all Rubric assertions will auto-fail.',
                $spec,
            ));

            return true;
        }

        if (! file_exists($spec)) {
            $command->error(sprintf(
                "--fake-judge spec '%s' is not 'pass', 'fail', or an existing file path",
                $spec,
            ));

            return false;
        }

        $contents = file_get_contents($spec);
        if ($contents === false) {
            $command->error(sprintf('--fake-judge file "%s" could not be read', $spec));

            return false;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true);
        if (! is_array($decoded)) {
            $command->error(sprintf('--fake-judge file "%s" does not contain a JSON array', $spec));

            return false;
        }

        /** @var list<string> $responses */
        $responses = [];
        foreach ($decoded as $index => $entry) {
            $encoded = json_encode($entry);
            if ($encoded === false) {
                $command->error(sprintf(
                    '--fake-judge file "%s" entry at index %s could not be encoded',
                    $spec,
                    (string) $index,
                ));

                return false;
            }
            $responses[] = $encoded;
        }

        $cursor = 0;
        $count = count($responses);
        JudgeAgent::fake(static function () use ($responses, &$cursor, $count, $spec): string {
            if ($cursor >= $count) {
                throw new RuntimeException(sprintf(
                    '--fake-judge file "%s" ran out of responses after %d invocation(s)',
                    $spec,
                    $count,
                ));
            }

            $response = $responses[$cursor];
            $cursor++;

            return $response;
        });

        $command->line(sprintf(
            'Using --fake-judge=%s — %d scripted response(s) loaded.',
            $spec,
            $count,
        ));

        return true;
    }
}
