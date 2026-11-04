<?php

namespace RayronVictor\ScrambleLaravelActions\Support;

use Dedoc\Scramble\Support\RouteInfo;
use Lorisleiva\Actions\Concerns\AsController;

/**
 * Knows how Laravel Actions turns an action into a controller, so the rest of the
 * package can reason about it the same way `ControllerDecorator` does at runtime.
 */
class ActionInspector
{
    /**
     * Methods that Laravel Actions treats as the "default" controller method. Routes
     * pointing to any other method are explicit and are not validated automatically.
     */
    public const DEFAULT_METHODS = ['asController', 'handle', '__invoke'];

    /**
     * Methods whose presence makes Laravel Actions validate the request before running the action.
     */
    public const VALIDATION_METHODS = ['rules', 'withValidator', 'afterValidator', 'getValidator'];

    public static function isAction(?string $className): bool
    {
        if (! $className || ! class_exists($className)) {
            return false;
        }

        return in_array(AsController::class, class_uses_recursive($className), true);
    }

    /**
     * Mirrors `ControllerDecorator::getDefaultRouteMethod()`.
     */
    public static function defaultRouteMethod(string $className): string
    {
        if (method_exists($className, 'asController')) {
            return 'asController';
        }

        return method_exists($className, 'handle') ? 'handle' : '__invoke';
    }

    /**
     * Whether the route is handled by an action through one of its default methods,
     * meaning Laravel Actions will validate the request and transform the response.
     */
    public static function isActionRoute(RouteInfo $routeInfo): bool
    {
        return static::isAction($routeInfo->className())
            && in_array($routeInfo->methodName(), static::DEFAULT_METHODS, true);
    }

    public static function hasValidationRules(string $className): bool
    {
        foreach (static::VALIDATION_METHODS as $method) {
            if (method_exists($className, $method)) {
                return true;
            }
        }

        return false;
    }
}
