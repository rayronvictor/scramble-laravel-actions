<?php

namespace RayronVictor\ScrambleLaravelActions\Tests\Fixtures;

use Lorisleiva\Actions\Concerns\AsAction;

class CountTodos
{
    use AsAction;

    public function handle(): int
    {
        return 42;
    }

    public function jsonResponse($count)
    {
        return response()->json(['count' => $count]);
    }

    public function custom(): array
    {
        return ['custom' => true];
    }

    public function rules(): array
    {
        return ['status' => ['required', 'string']];
    }
}
