<?php

namespace RayronVictor\ScrambleLaravelActions;

use Dedoc\Scramble\Contracts\RouteProvider;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Illuminate\Support\ServiceProvider;
use Lorisleiva\Actions\ActionRequest;
use RayronVictor\ScrambleLaravelActions\RouteProviders\ActionRouteProvider;

class ScrambleLaravelActionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend(RouteProvider::class, function (RouteProvider $routeProvider) {
            return $routeProvider instanceof ActionRouteProvider
                ? $routeProvider
                : new ActionRouteProvider($routeProvider);
        });
    }

    public function boot(): void
    {
        // `ActionRequest::rules()` only proxies the action's rules, which are documented
        // by `ActionRulesParametersExtractor` instead.
        FormRequestParametersExtractor::ignoreInstanceOf(ActionRequest::class);

        ScrambleLaravelActions::configure();
    }
}
