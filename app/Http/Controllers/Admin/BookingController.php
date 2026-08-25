<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $bookings = Booking::query()
            ->with(['user', 'items.equipment.category'])
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return BookingResource::collection($bookings);
    }

    public function confirm(Request $request, int $booking, BookingService $service): BookingResource
    {
        $booking = Booking::query()->findOrFail($booking);

        return new BookingResource($service->confirmPickup($booking)->load(['user', 'items.equipment.category']));
    }

    public function returnEquipment(Request $request, int $booking, BookingService $service): BookingResource
    {
        $booking = Booking::query()->findOrFail($booking);

        return new BookingResource($service->processReturn($booking)->load(['user', 'items.equipment.category']));
    }
}
