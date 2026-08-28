<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Equipment\StoreEquipmentRequest;
use App\Http\Requests\Equipment\UpdateEquipmentRequest;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use App\Services\PhotoStorageService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EquipmentController extends Controller
{
    public function __construct(private PhotoStorageService $photos)
    {
    }

    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo'] = $this->photos->store($request->file('photo'), 'equipment');
        } else {
            unset($data['photo']);
        }

        $equipment = Equipment::create($data);

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
        $data = $request->validated();
        unset($data['remove_photo']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $this->photos->replace(
                $request->file('photo'),
                'equipment',
                $equipment->photo,
            );
        } elseif ($request->boolean('remove_photo')) {
            $this->photos->delete($equipment->photo);
            $data['photo'] = null;
        } else {
            unset($data['photo']);
        }

        $equipment->update($data);

        return new EquipmentResource($equipment->refresh()->load('category'));
    }

    public function destroy(Equipment $equipment)
    {
        if ($equipment->bookingItems()->exists()) {
            return response()->json([
                'message' => 'Alat memiliki riwayat booking dan tidak dapat dihapus. Set stock ke 0 untuk menonaktifkan.',
            ], 409);
        }

        $this->photos->delete($equipment->photo);
        $equipment->delete();

        return response()->json(['message' => 'Alat berhasil dihapus.']);
    }
}
