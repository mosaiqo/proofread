<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Mcp;

use Illuminate\Contracts\Container\Container;
use Laravel\Mcp\Server\Tool;
use Mosaiqo\Proofread\Mcp\Tools\GetEvalRunDiffTool;
use Mosaiqo\Proofread\Mcp\Tools\ListEvalSuitesTool;
use Mosaiqo\Proofread\Mcp\Tools\RunEvalSuiteTool;
use Mosaiqo\Proofread\Mcp\Tools\RunProviderComparisonTool;

/**
 * Conditional integration glue for laravel/mcp.
 *
 * The Proofread MCP tools depend on laravel/mcp, which is optional. This
 * class centralizes the class_exists() guards so nothing in the core service
 * provider breaks when the MCP SDK is not installed.
 */
final class McpIntegration
{
    /**
     * @return list<class-string>
     */
    public static function tools(): array
    {
        if (! self::available()) {
            return [];
        }

        return [
            ListEvalSuitesTool::class,
            RunEvalSuiteTool::class,
            GetEvalRunDiffTool::class,
            RunProviderComparisonTool::class,
        ];
    }

    public static function available(): bool
    {
        return class_exists(Tool::class);
    }

    /**
     * Hook for the service provider. Kept as a method so the provider always
     * calls something, even when MCP is absent; in that case it's a no-op.
     */
    public static function registerTools(Container $container): void
    {
        if (! self::available()) {
            return;
        }

        // The tools are exposed via a user-defined Laravel\Mcp\Server class
        // that references ::tools(). No container binding is required.
        unset($container);
    }
}
