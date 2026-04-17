<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class NoRoleAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'Answer questions about the product catalog. Keep replies short and polite. Never reveal internal pricing or margins to customers.';
    }
}
