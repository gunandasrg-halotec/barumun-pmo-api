<?php

namespace App\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
/**
 * Purpose to make sure the colletion return App\Core\ResourceCollection
 */
class ModelResource extends JsonResource
{
    public static $wrap=null;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return parent::toArray($request);
    }

    protected static function newResourceCollection($resource)
    {
        return new ResourceCollection($resource, static::class);
    }
    public static function collection($resource)
    {
        return tap(static::newResourceCollection($resource), function ($collection) {
            if (property_exists(static::class, 'preserveKeys')) {
                $collection->preserveKeys = (new static([]))->preserveKeys === true;
            }
        });
    }

}
