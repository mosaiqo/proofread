---
title: Getting started
section: Start here
---

# Getting started

Proofread is the only eval package native to the official Laravel AI stack. It
measures whether your agents, prompts, and MCP tools actually do what you think
they do — from Pest, from CI, and from production traffic — without asking you
to leave Laravel.

Modern Laravel apps increasingly ship AI features straight to production. The
`laravel/ai` SDK makes them easy to build; Proofread makes them safe to change.

## Why Proofread

- **Pest-native expectations.** Write evals as `expect($agent)->toPassEval(...)`
  instead of learning a new YAML DSL.
- **Agent class-string FQCN as subject.** Point an eval at an FQCN, an instance,
  or a callable — `SubjectResolver` normalizes all of them, and `laravel/ai`
  fakes plug in automatically.
- **Shadow evals in production.** Capture real traffic, evaluate it
  asynchronously, alert on pass-rate drops, and promote failing captures into
  regression datasets.
- **CLI subjects.** Evaluate subscription-based providers (Claude Code, Codex,
  etc.) over their CLI, not an API — something API-only eval tools cannot do.

## What to read next

1. [Installation](/docs/installation) — get Proofread into your Laravel app.
2. [Quick start](/docs/quick-start) — a first eval in 5 minutes.
3. [Core concepts](/docs/core-concepts) — the vocabulary you will use daily.

> **[info]** Proofread is pre-1.0 and the API is still evolving. Pin a specific
> version in `composer.json` if you want stability across days.
