<?php

namespace RayronVictor\ScrambleLaravelActions\Tests\Fixtures;

use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Ping.
 */
class PingAction
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(): array
    {
        return ['pong' => true];
    }
}
