<?php

namespace RayronVictor\ScrambleLaravelActions\Tests\Fixtures;

use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a todo.
 *
 * Stores a new todo for the current user.
 */
class CreateTodo
{
    use AsAction;

    public function rules(): array
    {
        return [
            // The title of the todo.
            'title' => ['required', 'string', 'max:255'],
            'done' => ['sometimes', 'boolean'],
        ];
    }

    public function handle(string $title, bool $done = false): array
    {
        return ['id' => 1, 'title' => $title, 'done' => $done];
    }

    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->validated('title'), (bool) $request->validated('done', false));
    }

    public function jsonResponse(array $todo): TodoResource
    {
        return new TodoResource($todo);
    }
}
