const e=`---
title: Core concepts
section: Concepts
---

# Core concepts

Five building blocks power everything in Proofread. Internalize these and the
rest of the API reads as a thin layer on top.

## EvalSuite

A class with three abstract methods — \`dataset()\`, \`subject()\`, and
\`assertions()\` — plus optional lifecycle hooks. Suites are the unit of
reusability: one suite per thing you want to evaluate. See
[Eval suites](/docs/eval-suites) for the full contract.

## Dataset

An immutable, named list of cases. Each case is an array with an \`input\`,
an optional \`expected\`, and arbitrary \`meta\`. Datasets are content-addressed:
the same \`name\` with different cases produces a new "version" on
persist, so you can diff and compare runs across time.

## Subject

What gets evaluated. Proofread accepts four shapes interchangeably:

- A **callable** (closure or invokable).
- An **Agent class-string FQCN** — any class implementing
  \`Laravel\\Ai\\Contracts\\Agent\`; resolved from the container.
- An **Agent instance**.
- A **CliSubject** — wraps a subscription-based CLI provider (Claude Code,
  Codex, etc.) as if it were an agent.

The \`SubjectResolver\` normalizes all four. Tests swap between them without
changing assertion code.

## Assertion

The unit of verification. Implements a single \`run(mixed $output, array
$context): AssertionResult\` method. Proofread ships 15+ assertions across
deterministic, operational, semantic, trajectory, snapshot, structured, and
safety categories. Compose as many per case as you need. See
[Assertions](/docs/assertions).

## EvalRun

The result of running a suite. Aggregates one \`EvalResult\` per case, plus
overall pass/fail, pass count, pass rate, total cost, total latency, and any
JUnit/export output. When persisted, becomes a row in \`eval_runs\` that you
can compare with \`evals:compare\` or query via the Eloquent model. See
[Persistence](/docs/persistence).

## The flow

\`\`\`
EvalSuite
  ├── subject()      ──┐
  ├── dataset()      ──┼──> EvalRunner ──> EvalRun
  └── assertions()   ──┘                      │
                                              └──> [EvalResult per case]
                                                      ├── passed: bool
                                                      ├── output: mixed
                                                      └── assertion_results[]
\`\`\`

\`EvalRunner\` iterates the dataset, invokes the subject, runs every assertion
against the output, and aggregates results into an \`EvalRun\`. Everything else
in Proofread — Pest expectations, the \`evals:run\` command, shadow evaluation,
provider comparison — is a wrapper around this core loop.

> **[info]** The \`EvalRun\` returned by the runner is a value object. The
> \`EvalRun\` Eloquent model (same name, different namespace) is the persisted
> representation written when you pass \`--persist\`.
`;export{e as default};
