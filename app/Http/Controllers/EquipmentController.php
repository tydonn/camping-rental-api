<?php

namespace App\Http\Controllers;

use App\Http\Requests\Equipment\AvailabilityRequest;
use App\Http\Resources\AvailabilityResource;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use App\Services\AvailabilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EquipmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $equipment = Equipment::query()
            ->with('category')
            ->when($request->string('search')->trim(), fn (Builder $q, string $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->filled('category_id'), fn (Builder $q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('min_price'), fn (Builder $q) => $q->where('price_per_day', '>=', $request->float('min_price')))
            ->when($request->filled('max_price'), fn (Builder $q) => $q->where('price_per_day', '<=', $request->float('max_price')))
            ->when($request->boolean('available_only'), fn (Builder $q) => $q->where('stock', '>', 0))
            ->orderBy($request->input('sort_by', 'name'), $request->input('sort_dir', 'asc'))
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return EquipmentResource::collection($equipment);
    }

    public function show(Equipment $equipment): EquipmentResource
    {
        return new EquipmentResource($equipment->load('category'));
    }

    public function availability(Equipment $equipment, AvailabilityRequest $request, AvailabilityService $service): AvailabilityResource
    {
        $startDate = $request->date('start_date')->toDateString();
        $endDate = $request->date('end_date')->toDateString();
        $requestedQuantity = max((int) $request->integer('quantity', 1), 1);

        $booked = $service->bookedQuantity($equipment->id, $startDate, $endDate);
        $available = max($equipment->stock - $booked, 0);

        return new AvailabilityResource([
            'equipment_id' => $equipment->id,
            'name' => $equipment->name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'stock' => $equipment->stock,
            'booked' => $booked,
            'requested_quantity' => $requestedQuantity,
            'available' => $available,
            'is_available' => $available >= $requestedQuantity,
        ]);
    }
}
