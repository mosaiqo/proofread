<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Lint;

use InvalidArgumentException;
use Laravel\Ai\Contracts\Agent;
use Mosaiqo\Proofread\Lint\Contracts\LintRule;

final class PromptLinter
{
    /**
     * @param  list<LintRule>  $rules
     */
    public function __construct(
        private readonly array $rules,
    ) {}

    /**
     * @return list<LintRule>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    public function lintClass(string $agentClass): LintReport
    {
        if (! class_exists($agentClass)) {
            throw new InvalidArgumentException(
                sprintf('Agent class [%s] does not exist.', $agentClass)
            );
        }

        if (! is_a($agentClass, Agent::class, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Agent class [%s] does not implement [%s].',
                    $agentClass,
                    Agent::class,
                )
            );
        }

        /** @var Agent $agent */
        $agent = app($agentClass);

        $instructions = (string) $agent->instructions();

        $issues = [];
        foreach ($this->rules as $rule) {
            foreach ($rule->check($agent, $instructions) as $issue) {
                $issues[] = $issue;
            }
        }

        return new LintReport(
            agentClass: $agentClass,
            instructions: $instructions,
            issues: $issues,
        );
    }
}
