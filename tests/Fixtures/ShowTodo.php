<?php

namespace RayronVictor\ScrambleLaravelActions\Tests\Fixtures;

use Lorisleiva\Actions\Concerns\AsAction;

class ShowTodo
{
    use AsAction;

    /**
     * Show a todo.
     *
     * @param  int  $todo  The todo ID.
     */
    public function handle(int $todo): array
    {
        return ['id' => $todo, 'title' => 'Write docs'];
    }
}
