# Proofread visual language

This folder documents the design-token system that powers every
surface of the marketing site, from shadcn-vue primitives to raw
Tailwind utilities.

## Source of truth

`src/assets/styles/tokens.css` defines every primitive as a CSS
custom property on `:root` (light) and `.dark` (dark). At runtime,
theming works without JavaScript: toggling the `dark` class on
`<html>` is all that's needed.

`src/design/tokens.ts` re-exports the same primitives as typed
arrays so Vue components can introspect the catalog without
re-declaring values.

## Primitive categories

| Category  | CSS prefix        | Purpose                                      |
| --------- | ----------------- | -------------------------------------------- |
| Brand     | `--color-brand-*` | Laravel-red anchor (50 to 900).              |
| Accent    | `--color-accent-*`| Warm salmon companion scale.                 |
| Neutral   | `--color-neutral-*` | Warm-tinted grayscale (50 to 950).         |
| Semantic  | `--color-success`,`--color-warning`,`--color-danger`,`--color-info` | Feedback colors. |
| Surface   | `--color-bg-*`    | Page/elevated/muted surfaces (theme-aware).  |
| Foreground| `--color-fg-*`    | Default / muted / subtle text colors.        |
| Border    | `--color-border*` | Border + subtle-border hairlines.            |
| Typography| `--font-*`, `--text-*`, `--leading-*`, `--weight-*` | Families, scale, line-height, weight. |
| Spacing   | `--space-*`       | 4px base scale.                              |
| Radius    | `--radius-*`      | Corner scale `xs` through `full`.            |
| Shadow    | `--shadow-*`      | Three-tier elevation, theme-aware.           |
| Motion    | `--ease-*`, `--duration-*` | Canonical easings + timings.        |
| Z-index   | `--z-*`           | `dropdown`, `sticky`, `modal`, `toast`.      |

## How Tailwind consumes tokens

`tailwind.config.ts` maps each primitive into `theme.extend` using
`var(--token)` references. That means every Tailwind utility
(`bg-brand-500`, `text-muted-foreground`, `shadow-lg`, etc.)
resolves to the same custom property used by shadcn-vue components.

