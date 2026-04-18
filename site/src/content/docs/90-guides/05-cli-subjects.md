---
title: CLI subjects
section: Guides
---

# CLI subjects

Most subjects in Proofread are Laravel AI agents invoking an HTTP
provider API. CLI subjects swap that boundary for a subprocess: the
eval runner spawns a headless LLM CLI, feeds it the case input, and
parses the tool's output. The wire format changes, but the
assertion layer on top does not.

## When to reach for a CLI subject

The concrete wins show up in three situations:

- **Subscription pricing.** Developers running Anthropic Max or
  ChatGPT Plus are paying for a flat-rate seat. Invoking the same
  model through the API bills twice. Evaluating against the CLI
  reuses the subscription.
- **Local developer workflow.** If you already drive your agent
  through a CLI (for example, `claude -p` during prompt iteration),
  running evals against the same binary guarantees the eval and your
  interactive session see the exact same behaviour, without
  introducing a new set of API keys.
- **Offline-capable tools.** Some CLIs wrap local or self-hosted
  models. Treating them as subjects lets Proofread evaluate them
  without standing up an HTTP façade.

CLI subjects have real costs. Subprocess spawning is ~100× slower
than an HTTP call, most CLIs don't expose structured tool-call
traces, and CI authentication adds friction that a headless API key
does not. Use them where the savings or workflow benefits are
concrete; don't default to them.

## Claude Code reference implementation

`ClaudeCodeCliSubject` ships as the reference CLI subject, built on
top of the shared `CliSubject` base class. It targets the `claude`
binary in `--output-format json` mode so the runner can pull real
token usage and cost data out of the response rather than estimate.

```php
use Mosaiqo\Proofread\Cli\Subjects\ClaudeCodeCliSubject;

$subject = ClaudeCodeCliSubject::make()
    ->withModel('claude-sonnet-4-6')
    ->withTimeout(60)
    ->skipPermissions();
```

The builder API is immutable — every `withX` returns a new instance
— so subjects can be freely composed and shared.

| Method                   | Purpose                                                              |
| ------------------------ | -------------------------------------------------------------------- |
| `withBinary($path)`      | Absolute path or PATH-resolvable name. Default `claude`.             |
| `withModel($model)`      | Passed as `--model`. Omit to let the CLI pick its own.               |
| `withTimeout($seconds)`  | Process timeout. Default 120.                                        |
| `skipPermissions($bool)` | Adds `--dangerously-skip-permissions`. Off by default.               |
| `withArgs($extraArgs)`   | List of extra arguments appended after the built-in ones.            |
| `withEnv($vars)`         | Extra environment variables for the subprocess (merged, not replaced in host env). |

When the runner invokes the subject, the parsed response populates a
standard metadata bag on the resulting `CliInvocation`:

| Key                       | Source                                       |
| ------------------------- | -------------------------------------------- |
| `tokens_in`               | `usage.input_tokens`                         |
| `tokens_out`              | `usage.output_tokens`                        |
| `tokens_total`            | sum of the two above when both are present   |
| `cache_read_tokens`       | `usage.cache_read_input_tokens`              |
| `cache_creation_tokens`   | `usage.cache_creation_input_tokens`          |
| `cost_usd`                | `total_cost_usd`                             |
| `model`                   | `model`, falling back to first `modelUsage` key |
| `session_id`              | `session_id`                                 |
| `num_turns`               | `num_turns`                                  |
| `api_duration_ms`         | `duration_api_ms`                            |

Plus three fields added by the base class regardless of the concrete
subject: `cli_binary`, `cli_exit_code`, `cli_stderr` (truncated to
500 characters).

## Extending for other CLIs

Every CLI subject extends `Mosaiqo\Proofread\Cli\CliSubject` and
implements three methods:

```php
<?php

declare(strict_types=1);

namespace App\Cli;

use Mosaiqo\Proofread\Cli\CliResponse;
use Mosaiqo\Proofread\Cli\CliSubject;

final class GeminiCliSubject extends CliSubject
{
    public function binary(): string
    {
        return 'gemini';
    }

    public function args(string $prompt): array
    {
        return ['-p', $prompt, '--json'];
    }

    public function parseOutput(string $stdout, string $stderr): CliResponse
    {
        $decoded = json_decode(trim($stdout), true);

        return new CliResponse(
            output: (string) ($decoded['text'] ?? ''),
            metadata: [
                'tokens_in' => $decoded['usage']['input_tokens'] ?? null,
                'tokens_out' => $decoded['usage']['output_tokens'] ?? null,
                'model' => $decoded['model'] ?? null,
            ],
        );
    }

    public function timeout(): int
    {
        return 90;
    }
}
```

Optional overrides the base class already implements:

- `workingDirectory(): ?string` — `cwd` for the subprocess. Default
  `null` (inherit).
- `env(): array<string, string>` — extra env vars.
- `usesStdin(): bool` — when `true` the prompt is written to stdin
  instead of being passed as an argument. Default `false`.
- `estimateTokens(string $text): int` — heuristic used when the CLI
  doesn't report usage (`word_count / 0.75`).

`parseOutput()` decides what is and isn't an error: returning a
`CliResponse` means "normal output", throwing from `parseOutput()`
means "failure". Non-zero exit codes are not automatically fatal —
some CLIs use exit codes for partial-success states — so the
implementation makes the call.

## Invocation flow

Each run goes through a fixed pipeline inside `CliSubject::__invoke()`:

1. `args($prompt)` builds the argv array.
2. A `Symfony\Component\Process\Process` is constructed with that
   argv, the `workingDirectory()`, `env()`, and `timeout()`. If
   `usesStdin()` is true the prompt is piped to stdin instead.
3. The process runs. Time is measured with `hrtime(true)` and returned
   as `durationMs`.
4. `parseOutput()` converts `(stdout, stderr)` into a `CliResponse`.
5. The response's metadata is merged with the three `cli_*` fields
   and returned as a `CliInvocation`.

`SubjectResolver` detects a `CliSubject` instance and wraps it so
assertions receive the same `context` shape they do for agent
subjects. Existing assertions — `LatencyLimit`, `TokenBudget`,
`CostLimit` — work unchanged whenever the CLI exposes usage data.

## Error handling

Two dedicated exceptions, both extending `RuntimeException`:

- `CliTimeoutException` — the subprocess exceeded `timeout()`. The
  original `ProcessTimedOutException` is preserved as the previous.
- `CliExecutionException` — `parseOutput()` threw. The parser
  exception is the previous, and the message includes the observed
  exit code so you can tell "parser choked on bad JSON" from "CLI
  returned non-zero".

Both are unchecked — let them propagate to the runner, which records
them as case errors.

## Shell safety

`Process` is constructed from an argv array, not from a shell string.
Nothing is interpolated through `/bin/sh`, so prompts containing `$`,
backticks, `|`, `&&`, newlines, or quotes are passed verbatim to the
CLI without escape.

> **[success]** Injection-safe by construction. No manual
> `escapeshellarg()` is necessary — and adding it would corrupt
> prompts that legitimately contain those characters.

## Limitations

- **Throughput.** Spawning a process per case is ~100× slower than
  an HTTP call. Drop concurrency (`--concurrency=1`) for CLI-based
  runs and accept longer wall times.
- **CI auth.** The CLI must be pre-installed and authenticated in the
  runner. For subscription-backed tools that typically means shipping
  a session token as a secret, which defeats much of the cost
  advantage.
- **Structured subject output.** Most CLIs expose the final text
  only, not the intermediate tool calls or steps. Assertions that
  require the richer agent surface — `Trajectory`,
  `StructuredOutputAssertion` — may not work against a CLI subject.
- **Judges and embeddings.** `Rubric`, `Hallucination`, `Similar`,
  and friends still call their respective providers via the API.
  Using CLI subjects does not eliminate API usage for judged
  assertions.
- **Output-format drift.** The CLI's `--output-format` schema can
  change between releases. Pin the CLI version in CI and treat
  parser regressions as you would an SDK upgrade.

## Related

- [Running evals](/docs/07-running-evals) — the Pest
  `toPassAssertion` expectation and `EvalRunner::run()` accept a
  `CliSubject` anywhere a regular subject fits.
- [Cost simulation](/docs/90-guides/07-cost-simulation) — once CLI
  captures are flowing, the simulator can compare their real cost
  against an API-based alternative.
