<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class TooLongAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a helpful assistant. '.str_repeat('Provide extremely detailed explanations covering every possible edge case and nuance of the subject at hand, referencing historical context and modern developments alike. ', 80);
    }
}
