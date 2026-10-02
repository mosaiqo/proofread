<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Tests\TestCase;

uses(TestCase::class)->in('Feature');

uses()->group('dashboard')->in('Feature/Dashboard');
uses()->group('pulse')->in('Feature/Pulse');
