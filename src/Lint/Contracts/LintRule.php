<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Lint\Contracts;

use Laravel\Ai\Contracts\Agent;
use Mosaiqo\Proofread\Lint\LintIssue;

interface LintRule
{
    public function name(): string;

    /**
     * @return list<LintIssue>
     */
    public function check(Agent $agent, string $instructions): array;
}
