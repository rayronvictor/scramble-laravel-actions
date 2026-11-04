<?php

namespace RayronVictor\ScrambleLaravelActions;

use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Configuration\ParametersExtractors;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Scramble;
use RayronVictor\ScrambleLaravelActions\OperationExtensions\ActionResponseExtension;
use RayronVictor\ScrambleLaravelActions\OperationExtensions\PrepareActionOperationExtension;
use RayronVictor\ScrambleLaravelActions\ParameterExtractors\ActionRulesParametersExtractor;

class ScrambleLaravelActions
{
    /**
     * Registers the Laravel Actions support on a Scramble API configuration.
     *
     * The service provider does this for the default API, which every API registered
     * afterwards with `Scramble::registerApi()` inherits. Call it yourself for an API
     * that was registered before this package's service provider booted.
     */
    public static function configure(?GeneratorConfig $config = null): GeneratorConfig
    {
        return ($config ?? Scramble::configure())
            ->withParametersExtractors(function (ParametersExtractors $extractors) {
                $extractors->append(ActionRulesParametersExtractor::class);
            })
            ->withOperationTransformers(function (OperationTransformers $transformers) {
                $transformers
                    ->prepend(PrepareActionOperationExtension::class)
                    ->append(ActionResponseExtension::class);
            });
    }
}
