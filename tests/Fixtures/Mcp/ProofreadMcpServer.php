<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Tool;
use Mosaiqo\Proofread\Mcp\McpIntegration;

final class ProofreadMcpServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools;

    public function __construct(Transport $transport)
    {
        /** @var array<int, class-string<Tool>> $tools */
        $tools = McpIntegration::tools();
        $this->tools = $tools;

        parent::__construct($transport);
    }
}
