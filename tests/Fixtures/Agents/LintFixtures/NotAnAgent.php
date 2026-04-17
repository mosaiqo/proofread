<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures;

final class NotAnAgent
{
    public function hello(): string
    {
        return 'hi';
    }
}
