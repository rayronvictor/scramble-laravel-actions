<?php

namespace RayronVictor\ScrambleLaravelActions\Support;

use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Request;
use Lorisleiva\Actions\ActionRequest;
use WeakMap;

/**
 * `ActionRequest` extends `FormRequest`, so Scramble's `ErrorResponsesExtension` assumes every
 * action receiving it is validated (its `rules()` only proxies the action's rules). While an
 * action operation is being built, the `ActionRequest` arguments are shown as a plain
 * `Request`, and the real validation and authorization errors are attached from the action.
 */
class ActionRequestArguments
{
    /** @var WeakMap<FunctionType, array<string, Type>>|null */
    private static ?WeakMap $hidden = null;

    public static function hide(FunctionType $functionType): void
    {
        $hidden = static::$hidden ??= new WeakMap;

        if (isset($hidden[$functionType])) {
            return;
        }

        $arguments = $functionType->arguments;

        $hasActionRequest = false;

        foreach ($functionType->arguments as $name => $type) {
            if (static::isActionRequest($type)) {
                $functionType->arguments[$name] = new ObjectType(Request::class);
                $hasActionRequest = true;
            }
        }

        if ($hasActionRequest) {
            $hidden[$functionType] = $arguments;
        }
    }

    public static function restore(FunctionType $functionType): void
    {
        if (! static::$hidden || ! isset(static::$hidden[$functionType])) {
            return;
        }

        $functionType->arguments = static::$hidden[$functionType];

        unset(static::$hidden[$functionType]);
    }

    private static function isActionRequest(Type $type): bool
    {
        $type = $type instanceof TemplateType ? $type->is : $type;

        return $type instanceof ObjectType && $type->isInstanceOf(ActionRequest::class);
    }
}
