<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'hero_image' => $this->resource['hero_image'] ?? null,
            'maps_query' => $this->resource['maps_query'] ?? null,
            'address' => $this->resource['address'] ?? null,
            'phone' => $this->resource['phone'] ?? null,
            'email' => $this->resource['email'] ?? null,
            'hours' => $this->resource['hours'] ?? null,
        ];
    }
}