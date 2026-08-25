<?php

namespace App\Http\Controllers;

use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $bookings = Booking::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.equipment.category', 'payments' => fn ($q) => $q->latest('id')])
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return BookingResource::collection($bookings);
    }

    public function store(StoreBookingRequest $request, BookingService $service): JsonResponse
    {
        $booking = $service->createBooking(
            $request->user(),
            $request->date('start_date'),
            $request->date('end_date'),
            $request->validated('items'),
        );

        return (new BookingResource(
            $booking->load(['items.equipment.category', 'payments' => fn ($q) => $q->latest('id')]),
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, int $booking): BookingResource
    {
        $booking = Booking::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.equipment.category', 'payments' => fn ($q) => $q->latest('id')])
            ->findOrFail($booking);

        return new BookingResource($booking);
    }
}
