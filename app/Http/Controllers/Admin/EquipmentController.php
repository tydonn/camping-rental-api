<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Equipment\StoreEquipmentRequest;
use App\Http\Requests\Equipment\UpdateEquipmentRequest;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EquipmentController extends Controller
{
    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $equipment = Equipment::create($request->validated());

        return (new EquipmentResource($equipment->load('category')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Equipment $equipment): EquipmentResource
    {
        return new EquipmentResource($equipment->load('category'));
    }

    public function update(UpdateEquipmentRequest $request, Equipment $equipment): EquipmentResource
    {
        $equipment->update($request->validated());

        return new EquipmentResource($equipment->refresh()->load('category'));
    }

    public function destroy(Equipment $equipment)
    {
        if ($equipment->bookingItems()->exists()) {
            return response()->json([
                'message' => 'Alat memiliki riwayat booking dan tidak dapat dihapus. Set stock ke 0 untuk menonaktifkan.',
            ], 409);
        }

        $equipment->delete();

        return response()->json(['message' => 'Alat berhasil dihapus.']);
    }
}
