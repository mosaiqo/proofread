<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread;

class Proofread
{
    private static bool $pestExpectationsRegistered = false;

    public function version(): string
    {
        return '0.1.0-dev';
    }

    public static function registerPestExpectations(): void
    {
        if (self::$pestExpectationsRegistered) {
            return;
        }

        require_once __DIR__.'/Testing/expectations.php';

        self::$pestExpectationsRegistered = true;
    }
}
