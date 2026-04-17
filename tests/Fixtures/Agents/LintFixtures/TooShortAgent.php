<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class TooShortAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'Summarize.';
    }
}
