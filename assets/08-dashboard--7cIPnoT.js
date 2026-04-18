const e=`---
title: Dashboard
section: Guides
---

# Dashboard

The dashboard is the browser view onto everything the runtime
persists: suites that have run, cases that failed, datasets that
exist, comparisons you've published, costs accrued, and shadow
captures waiting to be promoted. It is read-only — nothing in the
UI triggers a new run — and lives under whatever path you configure,
gated by whatever policy you define.

## Enabling

The dashboard ships enabled by default and guarded by a Laravel gate
that allows only the \`local\` environment out of the box. That is
deliberately conservative: the UI surfaces production traffic, so
the package refuses to expose it by accident.

Relevant config keys in \`config/proofread.php\`:

\`\`\`php
'dashboard' => [
    'enabled' => env('PROOFREAD_DASHBOARD_ENABLED', true),
    'path' => env('PROOFREAD_DASHBOARD_PATH', 'evals'),
    'middleware' => ['web', 'proofread.gate'],
    'theme' => [
        // reserved for v1.5 branding
    ],
],
\`\`\`

- \`enabled\` — when \`false\`, the \`DashboardEnabled\` middleware 404s
  every dashboard route.
- \`path\` — URL prefix, defaulting to \`/evals\`.
- \`middleware\` — the stack applied after \`DashboardEnabled\`. The
  shipped default combines Laravel's \`web\` group with the
  \`proofread.gate\` alias (registered by the service provider and
  backed by the \`ProofreadGate\` middleware).

The default gate is defined only when the app has not already
registered one:

\`\`\`php
Gate::define('viewEvals', fn ($user = null) => app()->environment('local'));
\`\`\`

Override it in your \`AuthServiceProvider\` to ship the dashboard to
production users:

\`\`\`php
use Illuminate\\Support\\Facades\\Gate;

Gate::define('viewEvals', function ($user) {
    return $user?->can('view_evals');
});
\`\`\`

## Routes

The route file registers everything under the configured prefix and
names them \`proofread.*\`:

| Route                                  | Purpose                                              |
| -------------------------------------- | ---------------------------------------------------- |
| \`GET /{path}/overview\`                 | Home with trend chart, failing datasets, regressions |
| \`GET /{path}/runs\`                     | Paginated run list with filters                      |
| \`GET /{path}/runs/{run}\`               | Run detail with per-case drawer                      |
| \`GET /{path}/runs/{run}/export\`        | Download Markdown or HTML export                     |
| \`GET /{path}/compare\`                  | Side-by-side diff of two runs                        |
| \`GET /{path}/comparisons\`              | Multi-provider comparisons list                      |
| \`GET /{path}/comparisons/{comparison}\` | Matrix view with per-subject breakdown               |
| \`GET /{path}/comparisons/{comparison}/export\` | Download comparison export                    |
| \`GET /{path}/datasets\`                 | Datasets grid with sparklines                        |
| \`GET /{path}/costs\`                    | Breakdown by model + dataset + trend                 |
| \`GET /{path}/shadow\`                   | Shadow captures browser + promote-to-dataset         |

Hitting \`/{path}\` with no sub-path redirects to \`/{path}/overview\`.

## Runs detail

Each run page has three parts:

- **Header.** Dataset name, dataset version, run duration, total
  cost, overall status.
- **Summary.** Counts for pass / fail / error, plus the usual
  assertion-level totals derived from the persisted result rows.
- **Cases drawer.** Each case row is clickable; the drawer shows the
  input, the subject's output, per-assertion results, the metadata
  bag populated by the runner, and any error trace.

The drawer has a "Copy as failing test" action that emits a Pest
snippet matching the case. Paste it into a feature test to lock the
regression.

## Comparisons matrix

Multi-provider runs land on their own detail page. The layout is:

- **Winner cards.** Best pass rate, cheapest, and fastest pinned at
  the top.
- **Matrix.** Rows are cases, columns are subjects. Each cell shows
  pass / fail at a glance; clicking opens a drawer with per-subject
  output side by side.
- **Footer.** Pass rate, total cost, and average latency per subject
  so you don't need to scroll the matrix to compare totals.

The export endpoint renders the same matrix as self-contained HTML
suitable for sharing with stakeholders who do not have dashboard
access.

## Shadow panel

\`/evals/shadow\` lists captures with filters for agent class, status
(pending or evaluated), and free-text search. Clicking a row opens a
drawer showing the sanitized input, the output, all evaluations
already attached to the capture, and the raw metadata.

The "Promote to dataset" action generates a copy-paste PHP snippet
you add to your \`Dataset::make()\` block. Promotion is deliberately
manual — the package never mutates your dataset files.

## Exports

Both run and comparison detail pages expose export buttons:

- \`GET /evals/runs/{run}/export?format=md\` — Markdown, suitable for
  pasting into issue trackers.
- \`GET /evals/runs/{run}/export?format=html\` — self-contained HTML
  with embedded styles.
- \`GET /evals/comparisons/{comparison}/export?format=html\` — the
  matrix as an HTML document.

The \`Content-Disposition\` is always \`attachment\`, so browsers
download rather than render. Any other \`format\` value returns a 400.

## Trend chart

The \`trend-chart\` Blade component backs the SVG line charts on the
overview and costs pages. The overview chart shows a 30-day pass-rate
trend; the costs page shows a daily cost trend. The component
supports two Y-axis formats — \`percentage\` and \`currency\` — which
cover the two usages the package ships today.

## Production deployment

Production exposure is one-flag-on-one-policy-off:

- \`PROOFREAD_DASHBOARD_ENABLED=true\` (the default).
- Override \`viewEvals\` with your real authorization rule.

The \`web\` middleware is sufficient for session-based authentication.
If the dashboard sits behind an auth proxy, point the proxy at the
configured path and the gate still applies.

Storage is whatever the \`proofread\` connection resolves to — a
central DB for single-app setups, or your multi-tenant landlord
connection for split tenancies. Dashboard reads follow the same
connection.

> **[warn]** The dashboard is read-only. It does not trigger runs,
> mutate datasets, or change configuration. Promote-to-dataset emits
> a snippet for you to paste; nothing is written to disk on your
> behalf.

## Customizing the UI

Views are registered under the \`proofread::\` namespace and are
publishable:

\`\`\`bash
php artisan vendor:publish --tag=proofread-views
\`\`\`

Published files land under \`resources/views/vendor/proofread/\` and
take precedence over the package copies. Override a layout, swap the
trend chart for a heavier charting lib, retheme — the package never
reads your override, only the vendor copy, so changes stay local to
your app.

> **[info]** The Livewire components (\`Overview\`, \`RunsList\`,
> \`RunDetail\`, \`ComparisonsList\`, \`ComparisonDetail\`, \`DatasetsList\`,
> \`CompareRuns\`, \`CostsBreakdown\`, \`ShadowPanel\`) live in
> \`Mosaiqo\\Proofread\\Http\\Livewire\`. View overrides are the
> recommended customization surface; component overrides are
> possible but land in \`internal\` territory.

## Related

- [Running evals](/docs/07-running-evals) — the runs surfaced in the
  dashboard are written by the same runner the Pest expectation
  drives.
- [Shadow evals](/docs/90-guides/02-shadow-evals) — the shadow panel
  is the UI half of the pipeline described there.
- [Multi-provider comparison](/docs/90-guides/03-multi-provider) —
  the comparisons matrix is how you consume comparison runs without
  staring at JSON.
`;export{e as default};
