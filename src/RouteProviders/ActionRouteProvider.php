<?php

namespace RayronVictor\ScrambleLaravelActions\RouteProviders;

use Dedoc\Scramble\Attributes\Api;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Dedoc\Scramble\Contracts\RouteProvider;
use Dedoc\Scramble\GeneratorConfig;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RayronVictor\ScrambleLaravelActions\Support\ActionInspector;
use ReflectionMethod;

/**
 * Laravel Actions registers actions as invokable controllers (`Action@__invoke`) and, the
 * first time the route is dispatched, `ControllerDecorator` swaps the route method for
 * `asController` or `handle`. Scramble never dispatches routes, so it would document
 * the generic `__invoke` from the `AsController` trait.
 *
 * This provider does the same swap ahead of time, on a copy of each route, so Scramble
 * analyzes the method that really runs. The application's routes are left untouched.
 */
class ActionRouteProvider implements RouteProvider
{
    public function __construct(private RouteProvider $routeProvider) {}

    /**
     * @return Collection<int, Route>
     */
    public function get(GeneratorConfig $config): Collection
    {
        return $this->routeProvider
            ->get($config)
            ->map(fn (Route $route) => $this->resolveActionRoute($route, $config))
            ->filter()
            ->values();
    }

    private function resolveActionRoute(Route $route, GeneratorConfig $config): ?Route
    {
        $uses = $route->getAction('uses');

        if (! is_string($uses) || ! Str::contains($uses, '@')) {
            return $route;
        }

        [$className, $method] = explode('@', $uses, 2);

        if ($method !== '__invoke' || ! ActionInspector::isAction($className)) {
            return $route;
        }

        $routeMethod = ActionInspector::defaultRouteMethod($className);

        if ($routeMethod === '__invoke') {
            return $route;
        }

        if (! $this->shouldBeDocumented(new ReflectionMethod($className, $routeMethod), $config)) {
            return null;
        }

        $actionRoute = clone $route;
        $action = $actionRoute->getAction();
        $action['uses'] = $className.'@'.$routeMethod;

        return $actionRoute->setAction($action);
    }

    /**
     * Scramble checks these attributes on the route method before this provider runs, at
     * which point the method is still `__invoke`. Check them on the real method instead.
     */
    private function shouldBeDocumented(ReflectionMethod $method, GeneratorConfig $config): bool
    {
        if (count($method->getAttributes(ExcludeRouteFromDocs::class))) {
            return false;
        }

        if (! class_exists(Api::class) || ! $apiAttribute = ($method->getAttributes(Api::class)[0] ?? null)) {
            return true;
        }

        return in_array($config->name, $apiAttribute->newInstance()->only, true);
    }
}
