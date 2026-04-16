<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;
use Mosaiqo\Proofread\Shadow\EvalShadowMiddleware;
use Stringable;

class ShadowedEchoAgent implements Agent, HasMiddleware
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'Echo the user input back verbatim.';
    }

    /**
     * @return list<class-string>
     */
    public function middleware(): array
    {
        return [EvalShadowMiddleware::class];
    }
}
