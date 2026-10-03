const e=`---
title: "MCP tools"
section: "Integrations"
---

# MCP tools

Run Proofread evals from your MCP-compatible editor. Four tools ship
with the package; you plug them into your own \`Laravel\\Mcp\\Server\`
subclass and route them however you prefer (web or stdio).

## What is MCP

The Model Context Protocol is a standard for exposing tools to LLM
clients (Cursor, Claude Code, the MCP Inspector, and others). Laravel
exposes it via the \`laravel/mcp\` package, which lets packages ship
tool classes that consumers compose into their own MCP servers.

Proofread ships four tools that wrap the same runtime the Artisan
commands use: \`list_eval_suites\`, \`run_eval_suite\`, \`get_eval_run_diff\`
and \`run_provider_comparison\`.

## Installation

\`\`\`bash
composer require --dev laravel/mcp
\`\`\`

Proofread's tools are only loaded when the \`Laravel\\Mcp\\Server\\Tool\`
class exists. The \`Mosaiqo\\Proofread\\Mcp\\McpIntegration::available()\`
helper reflects that check, so nothing in the core service provider
breaks when the MCP SDK is not installed.

## Exposing Proofread tools in your Server

Create your Server subclass and merge Proofread's tools with your own:

\`\`\`php
namespace App\\Mcp;

use Laravel\\Mcp\\Server;
use Mosaiqo\\Proofread\\Mcp\\McpIntegration;

final class ProofreadMcpServer extends Server
{
    protected array $tools = [];

    public function __construct()
    {
        $this->tools = array_merge(McpIntegration::tools(), [
            // \\App\\Mcp\\Tools\\YourCustomTool::class,
        ]);
    }
}
\`\`\`

Route it via HTTP:

\`\`\`php
use Laravel\\Mcp\\Facades\\Mcp;

Mcp::web('/mcp/proofread', \\App\\Mcp\\ProofreadMcpServer::class);
\`\`\`

Or via stdio for CLI-style MCP clients:

\`\`\`php
Mcp::local('handle', \\App\\Mcp\\ProofreadMcpServer::class);
\`\`\`

Only suites listed in \`config('proofread.mcp.suites')\` are considered
discoverable by \`list_eval_suites\`. The other tools accept any
fully-qualified suite class the consumer points at.

## Shipped tools

### \`list_eval_suites\`

- **Input:** none.
- **Output:** \`{suites: [{name, class, dataset, case_count, subject}, ...]}\`.
- **Use:** discover which suites are registered as MCP-visible.

Only entries from \`config('proofread.mcp.suites')\` that extend
\`Mosaiqo\\Proofread\\Suite\\EvalSuite\` and instantiate cleanly are
returned.

### \`run_eval_suite\`

- **Input:**
  - \`suite_class\` (string, required) — FQCN of an \`EvalSuite\`.
  - \`persist\` (bool, default \`false\`) — write results through
    \`EvalPersister\`.
  - \`commit_sha\` (string, optional) — stamped onto the persisted run.
- **Output:** \`{suite_class, dataset_name, passed, total_cases,
  passed_count, failed_count, duration_ms, total_cost_usd,
  persisted_run_id, failures[]}\`.

Each failure entry has \`{case_index, case_name, assertions_failed[]}\`.
The list is truncated at 10 entries; when overflow happens the
response adds \`failures_truncated: true\` and \`failures_omitted: N\`.

### \`get_eval_run_diff\`

- **Input:**
  - \`base_run_id\` (string, required) — ULID of the base run.
  - \`head_run_id\` (string, required) — ULID of the head run.
- **Output:** the serialized \`EvalRunDelta\` — \`{dataset_name,
  regressions, improvements, stable_passes, stable_failures,
  cost_delta_usd, duration_delta_ms, has_regressions, cases[]}\`.

Both runs must share the same \`dataset_name\`. Case ordering
prioritizes \`regression\` and \`improvement\` entries so they survive
truncation. The list is capped at 50; overflow is signalled with
\`cases_truncated: true\` and \`cases_omitted: N\`.

### \`run_provider_comparison\`

- **Input:**
  - \`suite_class\` (string, required) — FQCN of a
    \`MultiSubjectEvalSuite\`.
  - \`persist\` (bool, default \`false\`) — writes through
    \`ComparisonPersister\`.
  - \`commit_sha\` (string, optional).
  - \`provider_concurrency\` (int, default \`0\`) — \`0\` runs all subjects
    in parallel, \`1\` runs sequentially, any other positive value caps
    parallelism.
- **Output:** \`{suite_class, name, dataset_name, passed, total_cases,
  duration_ms, persisted_comparison_id, subjects[], runs[]}\`.

Each entry in \`runs[]\` carries \`{subject_label, passed, total_cases,
passed_cases, failed_cases, pass_rate, cost_usd, duration_ms,
avg_latency_ms}\`. The full cases × subjects matrix is intentionally
omitted; use the dashboard or \`evals:export\` for that.

See the [multi-provider guide](/docs/guides/multi-provider) for how the
underlying \`ComparisonRunner\` builds these stats, and [running
evals](/docs/running-evals) for the semantics shared with
\`evals:run\`.

## Authentication

MCP tools inherit the middleware stack of the Server that hosts them.
For public deployments, layer \`Mcp::oauthRoutes()\` in front of
\`Mcp::web(...)\` and your own auth gate — Proofread does not add its
own gate beyond what the Laravel MCP package provides.

## Testing

The Laravel MCP package ships a fake transporter, so you can test the
tools without spinning up a real MCP client:

\`\`\`php
use Laravel\\Mcp\\Server\\Testing\\PendingTestResponse;

$response = MyMcpServer::tool(RunEvalSuiteTool::class, [
    'suite_class' => SentimentClassificationSuite::class,
    'persist'     => false,
]);

$response
    ->assertSee('sentiment-classification')
    ->assertStructuredContent(['passed' => true])
    ->assertHasNoErrors();
\`\`\`

No \`Mcp::fake()\` setup is required; the \`FakeTransporter\` is wired in
when you call the Server's \`::tool()\` helper.

> **[info]** MCP tool output is stateless. Persistence happens only
> when \`persist: true\` is passed — otherwise the run is computed,
> returned, and discarded.
`;export{e as default};
