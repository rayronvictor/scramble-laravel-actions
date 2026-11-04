<?php

namespace RayronVictor\ScrambleLaravelActions\ParameterExtractors;

use Dedoc\Scramble\Attributes\SchemaName;
use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\TypeBasedRulesDocumentationRetriever;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\GeneratesParametersFromRules;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\SchemaClassDocReflector;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use PhpParser\PrettyPrinter;
use RayronVictor\ScrambleLaravelActions\RulesEvaluators\ActionRulesEvaluator;
use RayronVictor\ScrambleLaravelActions\Support\ActionInspector;

/**
 * Documents the request parameters of an action from its `rules()` method, the same
 * way Scramble documents a `FormRequest`.
 */
class ActionRulesParametersExtractor implements ParameterExtractor
{
    use GeneratesParametersFromRules;

    public function __construct(
        private PrettyPrinter $printer,
        private TypeTransformer $openApiTransformer,
        private DiagnosticsCollector $diagnostics,
    ) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        if (! ActionInspector::isActionRoute($routeInfo)) {
            return $parameterExtractionResults;
        }

        $actionClassName = $routeInfo->className();

        if (! method_exists($actionClassName, 'rules')) {
            return $parameterExtractionResults;
        }

        $parameterExtractionResults[] = $this->extractActionParameters($actionClassName, $routeInfo);

        return $parameterExtractionResults;
    }

    private function extractActionParameters(string $actionClassName, RouteInfo $routeInfo): ParametersExtractionResult
    {
        $classReflector = ClassReflector::make($actionClassName);

        $reflection = $classReflector->getReflection();

        $phpDocReflector = SchemaClassDocReflector::createFromDocString($reflection->getDocComment() ?: '');

        $schemaNameAttribute = ($reflection->getAttributes(SchemaName::class)[0] ?? null)?->newInstance();

        $schemaName = ($phpDocReflector->getTagValue('@ignoreSchema')->value ?? null) !== null
            ? null
            : ($schemaNameAttribute
                ? ($schemaNameAttribute->input ?? $schemaNameAttribute->name)
                : $phpDocReflector->getSchemaName($actionClassName.'Request'));

        $rules = (new ActionRulesEvaluator(
            $this->printer,
            $classReflector,
            $routeInfo->method,
            $this->diagnostics->forClass($actionClassName),
            $routeInfo,
        ))->handle();

        return new ParametersExtractionResult(
            parameters: $this->makeParameters(
                rules: $rules,
                typeTransformer: $this->openApiTransformer,
                rulesDocsRetriever: new TypeBasedRulesDocumentationRetriever(
                    $routeInfo->getScope(),
                    new MethodCallReferenceType(new ObjectType($actionClassName), 'rules', []),
                ),
                in: in_array(mb_strtolower($routeInfo->method), RequestBodyExtension::HTTP_METHODS_WITHOUT_REQUEST_BODY)
                    ? 'query'
                    : 'body',
            ),
            schemaName: $schemaName,
            sourceClass: $actionClassName,
        );
    }
}
