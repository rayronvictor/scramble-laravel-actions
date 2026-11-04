<?php

namespace RayronVictor\ScrambleLaravelActions\Tests\Fixtures;

use Lorisleiva\Actions\Concerns\AsAction;

/**
 * List todos.
 */
class ListTodos
{
    use AsAction;

    /**
     * @response array{data: list<string>}
     */
    public function handle(): array
    {
        return ['data' => ['Write docs', 'Ship it']];
    }
}
