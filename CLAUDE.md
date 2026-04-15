# CLAUDE.md

Guidance for Claude Code sessions working in this repository.

## Project identity

- Package: `mosaiqo/proofread`
- Namespace: `Mosaiqo\Proofread\`
- Purpose: The only eval package native to the official Laravel AI stack. Evaluate agents, prompts, and MCP tools from Pest, CI, and production.
- Status: Early development, pre-v1, API unstable.

## Stack constraints

- PHP 8.4 only.
- Laravel 13.x only (no 12, no 14 yet).
- Pest v4 for tests.
- Orchestra Testbench v11 for package testing.
- `spatie/laravel-package-tools` for the service provider.
- `laravel/ai` pinned `~0.x` (tilde while the SDK is pre-1.0).
- `laravel/mcp` pinned `~0.x` in `require-dev` and `suggest`.
- `laravel/boost` in `suggest` only.

## Development workflow

Mandatory for every change:

- **TDD always.** Red -> green -> refactor per feature. Write the failing test first, then the minimum code to pass, then refactor. Do not skip the red phase even when the code seems trivial.
- **Commit per complete feature**, not per TDD cycle. Tests and implementation land together in one commit. The commit message describes the feature, not the TDD phase.
- **Work directly on `main`.** No PRs for now. Every commit on `main` must be green (tests + pint + phpstan).
- **Before committing:** run `composer test`, `composer format` (pint), and `composer analyse` (phpstan). All must pass.
- **Never mention Claude, Claude Code, or "Generated with" in commits, code, docs, or PRs.** No `Co-Authored-By: Claude` trailer. Hard rule.
- **Commits in English**, present tense, imperative ("Add X", not "Added X" or "Adds X").

## Code conventions

- All code, comments, docblocks, and documentation in English, regardless of conversation language.
- Strict types on every PHP file: `declare(strict_types=1);` at the top.
- Readonly value objects where applicable.
- Prefer named constructors (`make`, `fromArray`) over `new` for DX.
- No comments explaining WHAT (names should do that). Only comment WHY when non-obvious.
- Follow `laravel/pint` (preset `laravel`). Style enforced in CI.
- PHPStan level is configured in `phpstan.neon`. Keep it passing at the configured level; bumping the level is a separate deliberate change.

## Testing conventions

- Pest v4. Tests live in `tests/Feature/` and `tests/Unit/`.
- One test file per class under test. Mirror the `src/` structure inside `tests/Unit/`.
- Test names describe behavior: `it('returns a failed result when the needle is missing', ...)`, not `testContains`.
- Integration tests (those hitting Testbench's Laravel app) go in `tests/Feature/`.
- Pure unit tests (value objects, individual assertions) go in `tests/Unit/`.
- Avoid mocks for objects under test. Mock only external boundaries (HTTP, queue, LLM provider).

## File layout

```
src/
  Assertions/       # concrete assertions
  Contracts/        # interfaces (Assertion, Runner, etc.)
  Support/          # value objects, helpers
  ProofreadServiceProvider.php
tests/
  Unit/             # mirrors src/
  Feature/          # uses Testbench
  Pest.php
  TestCase.php
config/proofread.php
examples/           # sample dataset and example agent (shipped)
```

## Never do

- Never mention Claude in any artifact (code, commits, docs, PRs).
- Never disable or skip hooks (no `--no-verify`).
- Never use `git add -A` or `git add .` without first running `git status` to confirm what is being staged.
- Never add features beyond what the current task requires.
- Never add backwards-compat shims; this project is pre-v1 and breaking is fine.

## Informational: current focus

The first vertical slice of the runtime: `AssertionResult`, `Assertion` interface, `ContainsAssertion`, and the Pest `toPassAssertion` expectation. After that: `RegexAssertion`, `LengthAssertion`, `JsonSchemaAssertion`, then the `EvalRunner`. Work on whatever the user asks; this list is orientation only.
