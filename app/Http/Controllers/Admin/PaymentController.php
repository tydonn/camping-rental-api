<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->with(['verifier', 'booking.user'])
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->orderBy('created_at')
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return PaymentResource::collection($payments);
    }

    public function verify(Request $request, Payment $payment, PaymentService $service): PaymentResource
    {
        return new PaymentResource($service->verify($request->user(), $payment)->load('verifier'));
    }

    public function reject(Request $request, Payment $payment, PaymentService $service): PaymentResource
    {
        return new PaymentResource($service->reject($request->user(), $payment)->load('verifier'));
    }
}
