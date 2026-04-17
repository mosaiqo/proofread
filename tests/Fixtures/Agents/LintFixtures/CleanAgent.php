<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class CleanAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
You are a customer support agent for an e-commerce platform.
Answer user questions about orders, shipping, and returns in a polite, concise tone.
Always confirm the order ID before discussing any order details.
Never share personally identifiable information with third parties.
PROMPT;
    }
}
