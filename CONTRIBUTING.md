# Contributing to Proofread

Thanks for your interest in contributing. This document covers the
development workflow and conventions used in this repository.

## Project status

Proofread is pre-v1 and the API is still evolving based on dogfood
feedback. Breaking changes can land in minor versions until 1.0.0.

## Requirements

- PHP 8.4+
- Composer 2.x
- SQLite (default test driver; MySQL/PostgreSQL also supported)

## Local setup

```bash
git clone https://github.com/mosaiqo/proofread
cd proofread
composer install
composer test
```

## Development workflow

All changes follow a strict TDD loop: **red -> green -> refactor**.
Write a failing test first, then the minimum code to make it pass,
then refactor with the tests still green.

Commits are scoped to a complete feature: the test, the
implementation, and any related refactor land together. Commits
describe the feature, not the TDD phase.

Before committing, the full pipeline must pass:

```bash
composer test     # Pest v4 — expects 1200+ passing
composer format   # Pint with the Laravel preset
composer analyse  # PHPStan level 8
```

## Commit conventions

- English, present tense, imperative ("Add X", not "Added X").
- One feature per commit. Commits on `main` must be green.
- No trailers referencing AI assistants or generated content.

## Coding conventions

- `declare(strict_types=1);` on every PHP file.
- Readonly value objects where applicable. Named constructors
  (`make()`, `fromArray()`) preferred over `new`.
- No comments explaining **what** — names should. Only comment
  **why** when a constraint or decision is non-obvious.

## Test conventions

- Pest v4, one test file per class under test.
- `tests/Unit/` mirrors `src/` structure. `tests/Feature/` for tests
  that need Testbench.
- Avoid mocks for the class under test. Mock only external
  boundaries (HTTP, queue, LLM providers).
- Use `JudgeAgent::fake(...)` to stub the LLM judge.

## Releasing

Maintainers only:

1. Ensure `composer test/format/analyse` are all green on `main`.
2. Bump `Proofread::VERSION` in `src/Proofread.php`.
3. Add a `## [X.Y.Z] - YYYY-MM-DD` entry to `CHANGELOG.md`, grouped
   under `Added` / `Changed` / `Fixed` / `Deprecated` / `Removed`.
4. Update `UPGRADING.md` when a release has breaking changes or
   requires consumer action.
5. Commit: `Document X.Y.Z release in CHANGELOG, README, and VERSION constant`.
6. Tag: `git tag -a vX.Y.Z -m "Proofread X.Y.Z — <theme>"`.
7. Push tag: `git push origin vX.Y.Z`.
8. Create GitHub Release: `gh release create vX.Y.Z --title "X.Y.Z" --notes-from-tag`.

## Getting help

Open an issue at https://github.com/mosaiqo/proofread/issues.
