<?php

namespace RayronVictor\ScrambleLaravelActions\Tests\Fixtures;

use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Update a todo.
 */
class UpdateTodoWithInjectedRules
{
    use AsAction;

    public function rules(ActionRequest $request): array
    {
        return [
            'title' => ['string', Rule::unique('todos')->ignore($request->route('todo'))],
            'priority' => ['integer', 'min:1', 'max:5'],
        ];
    }

    public function handle(ActionRequest $request, int $todo): array
    {
        return ['id' => $todo, 'title' => (string) $request->validated('title')];
    }
}
