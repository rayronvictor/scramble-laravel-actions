<?php

namespace RayronVictor\ScrambleLaravelActions\Tests;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\ScrambleServiceProvider;
use Lorisleiva\Actions\ActionServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RayronVictor\ScrambleLaravelActions\ScrambleLaravelActionsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ActionServiceProvider::class,
            ScrambleServiceProvider::class,
            ScrambleLaravelActionsServiceProvider::class,
        ];
    }

    /**
     * Generates the OpenAPI document for the routes registered in the test.
     */
    protected function generateDocs(): array
    {
        return app(Generator::class)();
    }
}
