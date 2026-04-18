const e=`---
title: Dataset coverage analysis
section: Guides
---

# Dataset coverage analysis

A dataset is a set of claims about the shape of your production
traffic. Coverage analysis checks those claims against the captures
the shadow pipeline has been collecting and tells you two things: how
much of real traffic your fixtures actually represent, and which
patterns in the uncovered remainder deserve their own dataset cases.

## The coverage problem

The typical lifecycle looks like this:

1. You write twenty dataset cases during feature development.
2. CI goes green. Production ships.
3. Real users hit the agent with variations nobody thought of during
   design: 2FA recovery, partial refunds, warranty-scope edge cases.
4. The dataset keeps passing. The product keeps regressing.

The gap between "cases I wrote" and "traffic I receive" is the blind
spot. Coverage analysis surfaces it by embedding every dataset case
input and every recent shadow capture input, then attaching each
capture to its nearest case by cosine similarity. If the max
similarity meets a configurable threshold the capture is "covered";
otherwise it is a data point your dataset does not represent.
Uncovered captures are clustered so the output is "these three
topics are missing" rather than "seventy-eight unrelated strings".

## Running the command

\`\`\`bash
php artisan evals:coverage \\
    "App\\\\Agents\\\\SupportAgent" \\
    support-v1 \\
    --days=30 \\
    --threshold=0.70 \\
    --max-captures=500 \\
    --format=table
\`\`\`

Positional arguments:

- \`{agent}\` — FQCN of an agent that has shadow captures on file.
- \`{dataset}\` — the \`EvalDataset\` name to compare against. The
  latest \`EvalDatasetVersion\` is used (the immutable snapshot, not
  whatever is currently in code).

Flags:

| Flag                 | Default | Purpose                                                       |
| -------------------- | ------- | ------------------------------------------------------------- |
| \`--days\`             | \`30\`    | Window size, in days, counting back from now.                 |
| \`--threshold\`        | \`0.7\`   | Minimum cosine similarity to call a capture "covered".        |
| \`--max-captures\`     | \`500\`   | Hard cap on captures analysed. Protects against large bills.  |
| \`--embedding-model\`  | config  | Override \`proofread.similarity.default_model\`.                |
| \`--format\`           | \`table\` | \`table\` or \`json\`.                                            |

Exit codes: \`0\` on success (including "no captures to analyse"),
\`2\` on invalid arguments.

## How it works under the hood

1. \`CoverageAnalyzer::analyze()\` loads the dataset and picks its
   latest version. If the version has no cases the report is empty.
2. Shadow captures for the agent within the window are fetched,
   newest first, capped at \`maxCaptures\`. Captures with an empty or
   missing input are counted as skipped.
3. Cases and captures are embedded in a single
   \`Similarity::embed()\` call — fewer round-trips, one batch.
4. For each capture vector, \`Similarity::cosineFromVectors()\` is run
   against every case vector. The highest score wins.
5. Captures with a best score at or above the threshold increment
   the matched-count and similarity-sum for their nearest case.
   Captures below the threshold land in the \`uncovered\` list with
   their max similarity and nearest-case index preserved.
6. The uncovered set is handed to \`FailureClusterer::cluster()\` using
   the same threshold and embedding model, producing representative
   snippets plus member counts.

Everything else — the percentages, the recommendation line — is
derived from those four aggregates: total, covered, uncovered,
skipped.

## Output interpretation

A typical table-format run:

\`\`\`
Coverage report for App\\Agents\\SupportAgent vs dataset "support-v1"
Window: 2026-03-18 to 2026-04-17 (30 days)
Threshold: 0.70 cosine similarity

Summary:
  Total captures:    500
  Covered:           412 (82.4%)
  Uncovered:         78
  Skipped:           10 (no usable input)

Case coverage:
  Case | Name              | Matched | Avg similarity
  ---- | ----------------- | ------- | --------------
  0    | greeting          | 120     | 0.89
  1    | password_reset    | 84      | 0.82
  2    | refund_request    | 58      | 0.78
  3    | subscription_swap | 0       | 0.00

Uncovered clusters (3):

[Cluster 1] 32 captures, representative:
  "how do I reset my 2FA device after losing my phone..."

[Cluster 2] 29 captures, representative:
  "can I change my plan without losing the annual discount..."

[Cluster 3] 17 captures, representative:
  "warranty claim for a product bought through a reseller..."

Recommendation: add dataset cases covering how do i reset my 2fa device; can i change my plan without; warranty claim for a product.
\`\`\`

How to read each section:

- **Covered ratio** is the primary signal. Below 70% usually means
  the dataset was written without seeing real traffic. Above 90%
  means either good dataset hygiene or a threshold that is too loose.
- **Matched = 0** for a case means no capture in the window lands
  nearest to it. The case is either aspirational (a scenario users
  don't actually hit) or too narrowly worded to attract its own
  traffic. Consider rewording or removing.
- **Average similarity** on a matched case below ~0.75 indicates
  captures are landing on the case only because nothing else was
  closer. The case is semantically the best-of-bad-fits rather than
  a tight cluster.
- **Uncovered clusters** are the actionable output. Each cluster is a
  gap — ideally promoted to one or two new dataset cases before the
  next run.

## Thresholds

\`0.70\` is the default for \`text-embedding-3-small\` and works for most
English-text agents. Move it down toward \`0.60\` to be more permissive
(fewer uncovered captures, more noisy coverage claims), or up toward
\`0.80\` for stricter grouping.

Different embedding models produce different similarity distributions.
\`text-embedding-3-large\`, for example, tends to score semantically
related pairs a little higher than \`-small\` at the same prompt length.
If you swap models, recalibrate the threshold against a handful of
known pairs rather than assuming the number ports.

## Scheduling

Coverage analysis is not cheap. Every capture and every case gets
embedded each run, and embedding API calls cost tokens. A 500-capture
report against a 30-case dataset is ~530 embedding calls.

The command is intended for ad-hoc use after a meaningful volume of
traffic has accumulated, or as a weekly batch against a bounded
capture count. Pinning \`--max-captures\` is the most important cost
control; the default 500 is a sensible upper bound for most teams.

## Caveats

- **Requires embeddings access.** The Similarity service calls an
  embeddings provider. Without credentials, the command fails fast.
- **Skipped captures.** Counts captures with null or empty inputs
  after sanitization. A rising skipped count usually means your
  shadow middleware is stripping too much.
- **Snippet length.** Uncovered captures carry only the first 200
  characters of input as their snippet. Clusters operate on the
  snippet, not the full input.
- **One dataset version.** The latest version is used. If you need
  coverage for a pinned version, run the command shortly after that
  version was promoted.

## The closed-loop workflow

Coverage is one step in a loop that makes datasets track real traffic
rather than drift away from it:

1. Shadow capture collects sanitized production invocations.
2. \`evals:coverage\` surfaces uncovered clusters.
3. The dashboard's shadow panel lets you promote representative
   captures into dataset cases.
4. A new dataset version gets snapshotted and the suite re-runs.
5. Next coverage run shows the clusters are now covered, and any new
   gaps rise to the top.

> **[info]** Pair the coverage command with the shadow panel's
> promote-to-dataset flow (\`/evals/shadow\` in the dashboard) to close
> the loop without manual plumbing.

## Related

- [Shadow evals](/docs/90-guides/02-shadow-evals) — coverage depends
  on captures the shadow pipeline persisted.
- [Multi-provider comparison](/docs/90-guides/03-multi-provider) —
  once coverage is healthy, run the matrix against multiple models
  to see which one holds up across the whole traffic shape.
`;export{e as default};
