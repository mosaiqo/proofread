<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Mosaiqo\Proofread\Lint\LintIssue;
use Mosaiqo\Proofread\Lint\LintReport;
use Mosaiqo\Proofread\Lint\PromptLinter;
use Mosaiqo\Proofread\Lint\Rules\SemanticQualityRule;

final class LintCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'proofread:lint {agents* : FQCNs of Agent classes to lint}
        {--format=table : Output format: table, json, or markdown}
        {--severity=all : Minimum severity to report: all, info, warning, error}
        {--with-judge : Also apply the SemanticQualityRule (LLM-based analysis)}';

    /**
     * @var string
     */
    protected $description = 'Static analysis of Agent instructions: detect missing roles, ambiguity, contradictions, and more.';

    public function handle(PromptLinter $linter): int
    {
        /** @var array<int, string> $agents */
        $agents = (array) $this->argument('agents');

        $format = $this->resolveFormat();
        if ($format === null) {
            return 2;
        }

        $severity = $this->resolveSeverity();
        if ($severity === null) {
            return 2;
        }

        if ((bool) $this->option('with-judge')) {
            $linter = new PromptLinter([
                ...$linter->rules(),
                app(SemanticQualityRule::class),
            ]);
        }

        $reports = [];
        foreach ($agents as $agentClass) {
            try {
                $reports[] = $linter->lintClass($agentClass);
            } catch (InvalidArgumentException $exception) {
                $this->error($exception->getMessage());

                return 2;
            }
        }

        $filtered = array_map(
            fn (LintReport $report): LintReport => $this->filterReport($report, $severity),
            $reports,
        );

        return match ($format) {
            'json' => $this->renderJson($filtered),
            'markdown' => $this->renderMarkdown($filtered),
            default => $this->renderTable($filtered),
        };
    }

    private function resolveFormat(): ?string
    {
        $raw = $this->option('format');
        $format = is_string($raw) ? $raw : 'table';

        if (! in_array($format, ['table', 'json', 'markdown'], true)) {
            $this->error(sprintf(
                'Unsupported --format value "%s". Use "table", "json", or "markdown".',
                $format,
            ));

            return null;
        }

        return $format;
    }

    private function resolveSeverity(): ?string
    {
        $raw = $this->option('severity');
        $severity = is_string($raw) ? $raw : 'all';

        if (! in_array($severity, ['all', 'info', 'warning', 'error'], true)) {
            $this->error(sprintf(
                'Unsupported --severity value "%s". Use "all", "info", "warning", or "error".',
                $severity,
            ));

            return null;
        }

        return $severity;
    }

    private function filterReport(LintReport $report, string $severity): LintReport
    {
        if ($severity === 'all' || $severity === 'info') {
            return $report;
        }

        $keep = $severity === 'error'
            ? ['error']
            : ['error', 'warning'];

        $issues = array_values(array_filter(
            $report->issues,
            static fn (LintIssue $issue): bool => in_array($issue->severity, $keep, true),
        ));

        return new LintReport($report->agentClass, $report->instructions, $issues);
    }

    /**
     * @param  list<LintReport>  $reports
     */
    private function renderTable(array $reports): int
    {
        $agentsWithErrors = 0;

        foreach ($reports as $report) {
            $this->line(sprintf('Linting %s...', $report->agentClass));
            $this->line('');

            if (! $report->hasIssues()) {
                $this->line('  no issues detected');
                $this->line('');

                continue;
            }

            foreach ($report->issues as $issue) {
                $this->line($this->formatTableLine($issue));
                if ($issue->suggestion !== null) {
                    $this->line('         -> '.$issue->suggestion);
                }
            }

            $this->line('');
            $this->line(sprintf(
                'Summary: %d error(s), %d warning(s), %d info',
                $report->errorCount(),
                $report->warningCount(),
                $report->infoCount(),
            ));
            $this->line('');

            if ($report->hasErrors()) {
                $agentsWithErrors++;
            }
        }

        $this->line(sprintf(
            'Overall: %d agent(s) linted, %d with errors.',
            count($reports),
            $agentsWithErrors,
        ));

        return $agentsWithErrors > 0 ? 1 : 0;
    }

    private function formatTableLine(LintIssue $issue): string
    {
        $tag = '['.strtoupper($issue->severity).']';
        $tag = str_pad($tag, 9, ' ', STR_PAD_RIGHT);

        $ruleLabel = $issue->ruleName;
        if ($issue->lineNumber !== null) {
            $ruleLabel .= ' (line '.$issue->lineNumber.')';
        }
        $ruleLabel = str_pad($ruleLabel, 32, ' ', STR_PAD_RIGHT);

        return sprintf('  %s %s %s', $tag, $ruleLabel, $issue->message);
    }

    /**
     * @param  list<LintReport>  $reports
     */
    private function renderJson(array $reports): int
    {
        $totals = ['errors' => 0, 'warnings' => 0, 'infos' => 0, 'agents_with_errors' => 0];
        $agentsPayload = [];

        foreach ($reports as $report) {
            $agentsPayload[] = $report->toArray();
            $totals['errors'] += $report->errorCount();
            $totals['warnings'] += $report->warningCount();
            $totals['infos'] += $report->infoCount();
            if ($report->hasErrors()) {
                $totals['agents_with_errors']++;
            }
        }

        $payload = [
            'agents' => $agentsPayload,
            'overall' => $totals,
        ];

        $this->line((string) json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));

        return $totals['agents_with_errors'] > 0 ? 1 : 0;
    }

    /**
     * @param  list<LintReport>  $reports
     */
    private function renderMarkdown(array $reports): int
    {
        $agentsWithErrors = 0;
        $lines = [];
        $lines[] = '## Proofread prompt lint';
        $lines[] = '';

        foreach ($reports as $report) {
            $lines[] = sprintf('### `%s`', $report->agentClass);
            $lines[] = '';

            if (! $report->hasIssues()) {
                $lines[] = '_No issues detected._';
                $lines[] = '';

                continue;
            }

            $lines[] = '| Severity | Rule | Line | Message |';
            $lines[] = '|---|---|---|---|';
            foreach ($report->issues as $issue) {
                $lines[] = sprintf(
                    '| %s | `%s` | %s | %s |',
                    strtoupper($issue->severity),
                    $issue->ruleName,
                    $issue->lineNumber === null ? '-' : (string) $issue->lineNumber,
                    $this->escapeMarkdownCell($issue->message),
                );
            }
            $lines[] = '';
            $lines[] = sprintf(
                '**Summary:** %d error(s), %d warning(s), %d info.',
                $report->errorCount(),
                $report->warningCount(),
                $report->infoCount(),
            );
            $lines[] = '';

            if ($report->hasErrors()) {
                $agentsWithErrors++;
            }
        }

        $lines[] = sprintf(
            '**Overall:** %d agent(s) linted, %d with errors.',
            count($reports),
            $agentsWithErrors,
        );

        $this->line(implode("\n", $lines));

        return $agentsWithErrors > 0 ? 1 : 0;
    }

    private function escapeMarkdownCell(string $value): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], $value);
    }
}
