<?php

namespace RayronVictor\ScrambleLaravelActions\Tests\Fixtures;

use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Delete a todo.
 */
class DeleteTodo
{
    use AsAction;

    public function getControllerMiddleware(): array
    {
        return ['auth'];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(int $todo): void {}

    public function jsonResponse(): \Illuminate\Http\Response
    {
        return response()->noContent();
    }
}
