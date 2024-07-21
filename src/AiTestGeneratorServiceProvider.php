<?php

namespace Shawnveltman\AiTestGenerator;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Shawnveltman\AiTestGenerator\Commands\AiTestGeneratorCommand;

class AiTestGeneratorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('aitestgenerator')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_aitestgenerator_table')
            ->hasCommand(AiTestGeneratorCommand::class);
    }
}
