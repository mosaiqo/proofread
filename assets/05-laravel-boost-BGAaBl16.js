const e=`---
title: "Laravel Boost"
section: "Integrations"
---

# Laravel Boost

Proofread ships AI guidelines for Laravel Boost-powered editors.
Publish them into your repo, and Boost-aware assistants (Cursor,
Claude Code, and similar) generate idiomatic Proofread suites,
assertions, and datasets without tribal knowledge.

## What is Laravel Boost

Boost is an AI-first tooling layer for Laravel. Editors that consume
Boost guidelines pick them up as contextual rules when writing or
refactoring code in your project. The guidelines are plain Markdown
files — nothing runtime-bound; a suggest-only dependency.

## Publishing Proofread's guidelines

\`\`\`bash
php artisan vendor:publish --tag=proofread-boost-guidelines
\`\`\`

The file lands at \`.ai/guidelines/proofread.md\`. That path matches
the convention most Boost setups scan; adjust the destination if
your setup expects another location.

The stub source is
\`stubs/boost/proofread-guidelines.md\` inside the package, so you can
diff against upstream updates after a \`composer update\`.

## What the guidelines cover

- **\`EvalSuite\` structure** — naming (PascalCase + \`Suite\` suffix),
  kebab-case \`name()\`, lifecycle hooks (\`setUp\`, \`tearDown\`,
  \`assertionsFor\`).
- **Assertion selection** — when to reach for each category
  (string-based, regex, JSON, rubric, trajectory, similarity, etc.).
- **Testing patterns** — using \`JudgeAgent::fake()\` for deterministic
  rubric tests, \`toPassSuite\` over manual runner invocation.
- **CLI workflow** — \`evals:run\`, \`evals:benchmark\`,
  \`evals:compare\`, and the companion commands.
- **Scaffolding** — the \`proofread:make-suite\`,
  \`proofread:make-assertion\`, and \`proofread:make-dataset\`
  generators.

## Integration outcome

With the guidelines published, Boost-powered editors generate:

- Suites in the correct namespace and file layout.
- Named-constructor usage (\`Contains::make()\`, not
  \`new Contains()\`).
- Proper \`setUp\` / \`tearDown\` / \`assertionsFor\` hooks when database
  state is required.
- Pest expectations (\`toPassSuite\`, \`toPassEval\`) rather than hand-rolled
  runner invocations.

See the scaffolding notes in the [eval suites page](/docs/eval-suites)
for the patterns the guidelines enforce.

## Customizing

The published file is yours to edit. Adjust it to reflect your
stack's conventions — namespace roots, dataset directories, in-house
assertion packages — while keeping the Proofread-specific parts
intact so generation stays idiomatic.

> **[info]** Guidelines are project-scoped. Each consumer customizes
> their copy without affecting others. Proofread ships a neutral
> baseline; your repo's copy is the source of truth for your team.

## Relationship to scaffolding

The generators (\`proofread:make-*\`) produce canonical files from
stubs. Boost guidelines teach the AI to produce code *equivalent to*
those stubs when working in files outside the generator's reach. The
two workflows are complementary: generators for new suites,
guidelines for everything the assistant writes by hand.
`;export{e as default};
