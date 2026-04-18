---
title: Installation
section: Start here
---

# Installation

## Requirements

- PHP 8.4
- Laravel 13.x
- Pest v4 (for the Pest expectations; optional if you only use the CLI)

## Install the package

```bash
composer require mosaiqo/proofread
```

## Optional: MCP integration

If you want Proofread to expose eval tools over Model Context Protocol
(Claude Code, Cursor, and other MCP-compatible editors):

```bash
composer require laravel/mcp
```

## Publish migrations

The migrations create the `eval_datasets`, `eval_dataset_versions`,
`eval_runs`, and `eval_results` tables used by `--persist`.

```bash
php artisan vendor:publish --tag=proofread-migrations
php artisan migrate
```

## Publish the config (opt-in)

Only needed if you want to override defaults like the judge model, PII
sanitizer patterns, or shadow capture settings.

```bash
php artisan vendor:publish --tag=proofread-config
```

This writes `config/proofread.php` to your app.

## CI workflow scaffolding (optional)

```bash
php artisan vendor:publish --tag=proofread-workflows
```

Drops a ready-made GitHub Actions workflow into `.github/workflows/`.

> **[info]** Proofread uses the central database in multi-tenant setups.
> Tenant-scoped eval tables are not supported yet.

> **[warn]** If upgrading from a pre-0.3 version, see
> [UPGRADING.md](https://github.com/mosaiqo/proofread/blob/main/UPGRADING.md)
> for the migration rename.

## Next step

Head to the [Quick start](/docs/quick-start) to write your first eval.
