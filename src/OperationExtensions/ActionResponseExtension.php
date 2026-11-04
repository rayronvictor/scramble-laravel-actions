<?php

namespace RayronVictor\ScrambleLaravelActions\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\UniqueNameOptions;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use RayronVictor\ScrambleLaravelActions\Support\ActionInspector;
use RayronVictor\ScrambleLaravelActions\Support\ActionRequestArguments;

/**
 * Runs after Scramble's own operation extensions and adjusts the operation to what
 * Laravel Actions actually returns to an API client.
 */
class ActionResponseExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        if (! ActionInspector::isActionRoute($routeInfo)) {
            return;
        }

        if ($methodType = $routeInfo->getActionType()) {
            ActionRequestArguments::restore($methodType);
        }

        $this->useJsonResponse($operation, $routeInfo);

        $this->removeRouteMethodFromOperationId($operation, $routeInfo);
    }

    /**
     * When the action defines `jsonResponse($result, $request)`, Laravel Actions passes
     * the result of the action through it for JSON requests, so that is what an API
     * client receives instead of the raw result.
     */
    private function useJsonResponse(Operation $operation, RouteInfo $routeInfo): void
    {
        $actionClassName = $routeInfo->className();

        if (! method_exists($actionClassName, 'jsonResponse')) {
            return;
        }

        $resultType = $routeInfo->getReturnType() ?? new UnknownType;

        $jsonResponseType = ReferenceTypeResolver::getInstance()->resolve(
            $routeInfo->getScope(),
            new MethodCallReferenceType(new ObjectType($actionClassName), 'jsonResponse', [
                $resultType,
                new ObjectType(ActionRequest::class),
            ]),
        );

        $jsonResponses = $this->toResponses($jsonResponseType);

        if ($jsonResponses->isEmpty()) {
            return;
        }

        $replacedKeys = $this->toResponses($resultType)->map($this->responseKey(...))->all();
        $replacedCodes = $jsonResponses->map(fn ($response) => $response instanceof Response ? $response->code : null)->filter()->all();

        $otherResponses = collect($operation->responses ?? [])->reject(
            fn ($response) => in_array($this->responseKey($response), $replacedKeys, true)
                || ($response instanceof Response && in_array($response->code, $replacedCodes, true))
        );

        $operation->responses = $jsonResponses
            ->merge($otherResponses)
            ->unique($this->responseKey(...))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Response|Reference>
     */
    private function toResponses(Type $type): Collection
    {
        return collect($type instanceof Union ? $type->types : [$type])
            ->map($this->openApiTransformer->toResponse(...))
            ->filter()
            ->values();
    }

    private function responseKey(Response|Reference $response): string
    {
        return ($response instanceof Response ? $response->code : 'ref').':'.json_encode($response->toArray());
    }

    /**
     * Scramble removes `__invoke` from generated operation IDs. Do the same for the
     * methods Laravel Actions routes to, so `CreateUser@handle` becomes `createUser`.
     */
    private function removeRouteMethodFromOperationId(Operation $operation, RouteInfo $routeInfo): void
    {
        $operationId = $operation->getAttribute('operationId');

        if (! $operationId instanceof UniqueNameOptions) {
            return;
        }

        $unique = $operationId->unique;

        if (! in_array(end($unique), ['handle', 'asController'], true)) {
            return;
        }

        array_pop($unique);

        $operation->setAttribute('operationId', new UniqueNameOptions(
            eloquent: $operationId->eloquent,
            unique: $unique,
            separator: $operationId->separator,
            fallbackEloquentPartsCount: $operationId->fallbackEloquentPartsCount,
        ));
    }
}
