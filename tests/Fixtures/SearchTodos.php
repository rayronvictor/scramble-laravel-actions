<?php

namespace RayronVictor\ScrambleLaravelActions\Tests\Fixtures;

use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Search todos.
 */
class SearchTodos
{
    use AsAction;

    public function asController(ActionRequest $request): array
    {
        return [
            // The search term.
            'query' => $request->input('q'),
        ];
    }
}
