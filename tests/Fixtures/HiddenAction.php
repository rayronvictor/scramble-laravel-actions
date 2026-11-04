<?php

namespace RayronVictor\ScrambleLaravelActions\Tests\Fixtures;

use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Lorisleiva\Actions\Concerns\AsAction;

class HiddenAction
{
    use AsAction;

    #[ExcludeRouteFromDocs]
    public function handle(): array
    {
        return ['secret' => true];
    }
}
