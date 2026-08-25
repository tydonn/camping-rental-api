<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * Pelanggan melaporkan pembayaran untuk booking berstatus pending.
     */
    public function submitPayment(User $user, Booking $booking, string $method, ?string $note): Payment
    {
        if ($booking->user_id !== $user->id) {
            abort(404);
        }

        if ($booking->status !== Booking::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'booking' => ['Pembayaran hanya dapat dilaporkan untuk booking berstatus pending.'],
            ]);
        }

        $hasOpenPayment = $booking->payments()
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_VERIFIED])
            ->exists();

        if ($hasOpenPayment) {
            throw ValidationException::withMessages([
                'booking' => ['Sudah ada laporan pembayaran yang menunggu verifikasi atau sudah terverifikasi.'],
            ]);
        }

        return DB::transaction(function () use ($booking, $method, $note) {
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'method' => $method,
                'status' => Payment::STATUS_PENDING,
                'note' => $note,
                'amount' => $booking->total_amount,
                'paid_at' => now(),
            ]);

            return $payment;
        });
    }

    /**
     * Admin memverifikasi pembayaran: payment verified, booking menjadi paid.
     */
    public function verify(User $admin, Payment $payment): Payment
    {
        if ($payment->status !== Payment::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'payment' => ['Hanya pembayaran berstatus pending yang dapat diverifikasi.'],
            ]);
        }

        return DB::transaction(function () use ($admin, $payment) {
            $payment->update([
                'status' => Payment::STATUS_VERIFIED,
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]);

            $payment->booking()->update([
                'status' => Booking::STATUS_PAID,
            ]);

            return $payment;
        });
    }

    /**
     * Admin menolak pembayaran: booking tetap pending agar pelanggan bisa melapor ulang.
     */
    public function reject(User $admin, Payment $payment): Payment
    {
        if ($payment->status !== Payment::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'payment' => ['Hanya pembayaran berstatus pending yang dapat ditolak.'],
            ]);
        }

        return DB::transaction(function () use ($admin, $payment) {
            $payment->update([
                'status' => Payment::STATUS_REJECTED,
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]);

            $payment->booking()->update([
                'status' => Booking::STATUS_PENDING,
            ]);

            return $payment;
        });
    }
}
