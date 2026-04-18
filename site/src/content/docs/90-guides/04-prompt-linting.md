---
title: Prompt linting
section: Guides
---

# Prompt linting

Prompts are production code with no compiler behind them. A contradiction
that slips into a system instruction will not blow up in CI — it will
quietly degrade the agent's behaviour until somebody notices an
assertion flaking or a shadow eval alert firing. The linter is the
cheap, fast, offline pass that lives one rung below assertions in the
evaluation stack: static analysis for your instructions, with an
optional LLM-backed semantic check for the subjective bits a regex
will never catch.

## Why lint prompts

Code linters gained adoption because "obviously wrong" patterns are
worth reporting automatically, even when they don't strictly break a
build. The same holds for prompts:

- **Ambiguity.** Hedging language (`maybe`, `when appropriate`) leaves
  the model to improvise and produces inconsistent outputs.
- **Contradictions.** "Always escalate tier-2 issues" two paragraphs
  above "Never escalate without explicit user consent" is a bug the
  model will silently split the difference on.
- **Missing role.** The first paragraph should anchor who the agent is.
  Without it the model picks its own persona per invocation.
- **Missing output format.** Agents that declare a structured-output
  schema but never mention JSON in the prompt end up producing free
  text that fails parsing.

The deterministic rules run in milliseconds. The optional
`SemanticQualityRule` hits the judge agent for a subjective quality
critique — slower, not free, opt-in.

## Running the linter

Single agent:

```bash
php artisan proofread:lint "App\\Agents\\SupportAgent"
```

Multiple agents in one invocation:

```bash
php artisan proofread:lint \
    "App\\Agents\\SupportAgent" \
    "App\\Agents\\BillingAgent" \
    "App\\Agents\\TriageAgent"
```

Flags:

| Flag             | Values                       | Default | Purpose                                              |
| ---------------- | ---------------------------- | ------- | ---------------------------------------------------- |
| `--format`       | `table`, `json`, `markdown`  | `table` | Output shape. Markdown is designed for PR comments.  |
| `--severity`     | `all`, `info`, `warning`, `error` | `all`   | Minimum severity to report.                          |
| `--with-judge`   | flag                         | off     | Also apply `SemanticQualityRule` (LLM-based).        |

Exit codes:

- `0` — no issues at error severity.
- `1` — at least one agent had errors. Use this in CI gates.
- `2` — invalid argument (class not found, not an `Agent`, bad flag value).

## Built-in deterministic rules

Five rules ship with the package and are applied in order. Each
produces zero or more `LintIssue` instances, which carry a severity
(`error`, `warning`, `info`), a message, an optional suggestion, and
an optional line number.

### LengthRule

Warns when instructions are shorter than 50 characters or longer than
10 000. Very short instructions rarely give the model enough context;
very long ones often signal a god-agent that should be split into
sub-agents.

Both bounds produce a `warning`. Neither is an error — the rule exists
to surface outliers, not to dictate style.

### MissingRoleRule

Looks at the first paragraph of the instructions for one of four role
markers: `you are`, `act as`, `your role`, `as a`/`as an`. If none is
present, emits a warning suggesting the agent open with a role line.

The rule is deliberately lenient: anything that reads like a role
definition passes. False positives are possible when the first
paragraph happens to contain one of the markers in a non-role sense;
in practice this is rare, and the suggestion ("start with 'You are
a…'") is a useful nudge even then.

### AmbiguityRule

Scans every line for eight hedging phrases:

```
maybe, perhaps, if possible, try to, should try,
might want, could potentially, when appropriate
```

Each match is reported as an `info` issue with the matching line number,
so the CI output points directly at the offending line. Multiple hedges
on the same line produce one issue each.

Downgrading from `warning` to `info` is intentional: hedging is not
always wrong (some agents genuinely operate in "try X, fall back to
Y" territory), but every instance is worth eyeballing.

### ContradictionRule

Extracts phrases following `always` and `never` and flags potential
contradictions between them. The heuristic compares content-word sets
of the shorter phrase against the longer one:

```
overlap_ratio = |shorter ∩ longer| / |shorter|
```

Ratio above 0.5 triggers an `error`.

Shorter-phrase containment is used rather than symmetric Jaccard
overlap because scoped exceptions ("never X when Y") still contradict
the unconditional rule ("always X"). With Jaccard, the `when Y`
dilutes the overlap and the contradiction slips through.

Limitations: stop-word stripping is English-only, and phrases are
delimited by `.`, `,`, `;`, `:`, newline. Multilingual instructions or
contradictions expressed across multiple sentences can be missed.

### MissingOutputFormatRule

Only fires for agents implementing `Laravel\Ai\Contracts\HasStructuredOutput`.
It checks the lowercased instructions for any of five format tokens:
`json`, `schema`, `structured`, `format`, `shape`. If none is present,
emits a warning reminding you to tell the model about the expected
shape — otherwise the structured-output enforcement can end up
repairing free-text responses that weren't written with schema in mind.

## Sample output

Running the linter against an agent with a role issue, a hedge, and a
contradiction:

```
Linting App\Agents\SupportAgent...

  [WARNING] missing_role                     Instruction does not appear to define the agent's role or persona. Consider starting with 'You are a...' or similar.
         -> Open with a role line like 'You are a support agent for ...' to anchor the model.
  [INFO]    ambiguity (line 7)               Hedging phrase 'when appropriate' may cause inconsistent behavior. Consider rephrasing to an explicit rule.
         -> Replace 'when appropriate' with an explicit instruction or drop the phrase.
  [ERROR]   contradiction                    Potential contradiction: 'always escalate tier-2 issues' vs 'never escalate without explicit user consent'. Reconcile these rules.
         -> Remove one rule or scope each to the cases where it applies.

Summary: 1 error(s), 1 warning(s), 1 info

Overall: 1 agent(s) linted, 1 with errors.
```

## Semantic quality rule (optional)

`--with-judge` appends `SemanticQualityRule` to the default rule set.
The rule sends the instructions to the configured judge agent with
a structured-output prompt:

```
Evaluate the quality of an AI agent's system instruction.
Check for: clarity, specificity, absence of contradictions, clear
task definition, output format specification.

INSTRUCTION:
<instruction body>

Respond with ONLY a JSON object of this exact shape, no preamble:
{"passed": <boolean>, "score": <number between 0 and 1>, "reason": "<one-sentence summary>", "issues": ["<specific issue>", ...]}
```

The judge's response is parsed into issues:

- Each entry in `issues[]` becomes a `warning`.
- A `score` below `0.7` produces an `error` including the judge's reason.

The rule retries once on malformed JSON before giving up. Any other
failure (judge unreachable, provider 5xx, time-out) is caught and
reported as a single "Semantic analysis unavailable" warning — the
linter never crashes because the judge misbehaved.

Cost caveat: every `--with-judge` run hits the judge agent once per
linted agent class. For tight CI loops, keep this behind a
`--with-judge` stage that only runs on prompt changes, not every
commit.

## CI integration

The markdown format is designed for PR comments or GitHub workflow
step summaries:

```bash
php artisan proofread:lint $AGENTS \
    --format=markdown \
    --severity=warning \
    > /tmp/lint.md
```

Pair with a job that fails on exit code 1:

```yaml
- name: Lint prompts
  run: |
    php artisan proofread:lint \
        "App\\Agents\\SupportAgent" \
        --format=markdown \
        --severity=warning \
        | tee -a $GITHUB_STEP_SUMMARY
```

Severity `warning` suppresses `info` (mostly hedging noise) so the PR
comment focuses on real findings without losing errors.

## Writing custom lint rules

A rule is any class implementing
`Mosaiqo\Proofread\Lint\Contracts\LintRule`:

```php
<?php

declare(strict_types=1);

namespace App\Lint;

use Laravel\Ai\Contracts\Agent;
use Mosaiqo\Proofread\Lint\Contracts\LintRule;
use Mosaiqo\Proofread\Lint\LintIssue;

final class NoShoutingRule implements LintRule
{
    public function name(): string
    {
        return 'no_shouting';
    }

    public function check(Agent $agent, string $instructions): array
    {
        if ($instructions !== '' && strtoupper($instructions) === $instructions) {
            return [LintIssue::warning(
                ruleName: $this->name(),
                message: 'Instructions are entirely uppercase.',
                suggestion: 'Use normal casing; uppercase does not increase emphasis for the model.',
            )];
        }

        return [];
    }
}
```

`LintIssue` has three named constructors — `error`, `warning`, `info` —
each accepting `ruleName`, `message`, optional `suggestion` and
optional `lineNumber`.

Register the rule by extending the `PromptLinter` singleton in a
service provider:

```php
use Mosaiqo\Proofread\Lint\PromptLinter;

$this->app->extend(PromptLinter::class, fn (PromptLinter $linter) =>
    new PromptLinter([...$linter->rules(), new NoShoutingRule])
);
```

> **[info]** The `proofread:lint` command resolves `PromptLinter` out
> of the container with the five built-in rules. Extending the
> singleton propagates custom rules to every consumer — the CLI, any
> tests calling `PromptLinter::lintClass()`, and anything else that
> injects the service.

## Related

- [Assertions deep dive](/docs/90-guides/01-assertions-deep-dive) — when
  lint output points at a structural issue, the fix is often a new
  assertion guarding the behaviour the prompt was supposed to enforce.
- [Multi-provider comparison](/docs/90-guides/03-multi-provider) —
  linting catches static issues; comparison tells you whether the
  prompt survives model migration.
