<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'equipment_id' => $this['equipment_id'],
            'name' => $this['name'],
            'start_date' => $this['start_date'],
            'end_date' => $this['end_date'],
            'stock' => $this['stock'],
            'booked' => $this['booked'],
            'requested_quantity' => $this['requested_quantity'],
            'available' => $this['available'],
            'is_available' => $this['is_available'],
        ];
    }
}
