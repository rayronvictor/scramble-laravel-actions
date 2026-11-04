<?php

namespace RayronVictor\ScrambleLaravelActions\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class TodoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->resource['id'],
            'title' => (string) $this->resource['title'],
            'done' => (bool) $this->resource['done'],
        ];
    }
}
