<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Booking;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Booking $booking, PaymentService $service): JsonResponse
    {
        $payment = $service->submitPayment(
            $request->user(),
            $booking,
            $request->validated('method'),
            $request->validated('note'),
        );

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
