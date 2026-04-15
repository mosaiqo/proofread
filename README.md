# Proofread

> The only eval package native to the official Laravel AI stack.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mosaiqo/proofread.svg?style=flat-square)](https://packagist.org/packages/mosaiqo/proofread)
[![Tests](https://img.shields.io/github/actions/workflow/status/mosaiqo/proofread/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mosaiqo/proofread/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/mosaiqo/proofread.svg?style=flat-square)](LICENSE)

> **Status:** Early development — pre-v1, API unstable. Expect breaking changes.

## Why Proofread

Modern Laravel apps increasingly ship AI agents, prompts, and MCP tools straight
to production. The official Laravel AI SDK makes building them easy, but the
feedback loop for *evaluating* them has lived outside the framework in
language-agnostic tools like Promptfoo. Proofread brings that loop home: a
Laravel-native way to measure whether your agents actually do what you think
they do, from Pest, from CI, and from production traffic, with first-class
support for MCP tool evals and LLM-as-judge scoring.

## Installation

```bash
composer require mosaiqo/proofread
```

## Quick start

```php
// TODO: replace with real Pest expectation once the runner lands.
it('classifies a support ticket as billing', function (): void {
    expect($agent)->toAnswer('My card was charged twice')->matching('billing');
});
```

## Roadmap

Tracked in [GitHub issues](https://github.com/mosaiqo/proofread/issues).

## Contributing

PRs welcome. Run the test suite with:

```bash
composer test
```

Format code with:

```bash
composer format
```

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

## Credits

- [Boudy de Geer](https://github.com/boudy) / [Mosaiqo](https://mosaiqo.com)
