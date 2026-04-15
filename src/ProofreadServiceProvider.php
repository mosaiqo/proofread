<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ProofreadServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('proofread')
            ->hasConfigFile();
    }
}
