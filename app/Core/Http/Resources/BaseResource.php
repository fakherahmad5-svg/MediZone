<?php

namespace App\Core\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{

    public static $wrap = null;

    protected function formatDate(?\Carbon\Carbon $date): ?string
    {
        return $date?->toIso8601String();
    }

    protected function formatDateOnly(?\Carbon\Carbon $date): ?string
    {
        return $date?->toDateString();
    }

    protected function whenLoaded(string $relation, callable $callback): mixed
    {
        if ($this->relationLoaded($relation)) {
            return $callback();
        }

        return $this->when(false, null);
    }
}
