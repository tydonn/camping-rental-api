<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Equipment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private readonly AvailabilityService $availability)
    {
    }

    /**
     * Buat booking berstatus pending beserta item-itemnya.
     * Subtotal dihitung sebagai snapshot harga saat booking dibuat.
     */
    public function createBooking(User $user, string|CarbonInterface $startDate, string|CarbonInterface $endDate, array $items): Booking
    {
        return DB::transaction(function () use ($user, $startDate, $endDate, $items) {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->startOfDay();
            $startDateString = $startDate->toDateString();
            $endDateString = $endDate->toDateString();

            $equipments = Equipment::query()
                ->whereIn('id', collect($items)->pluck('equipment_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $equipment = $equipments[$item['equipment_id']];
                $available = $this->availability->bookedQuantity($equipment->id, $startDateString, $endDateString);
                $free = max($equipment->stock - $available, 0);

                if ($free < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Stok {$equipment->name} tidak mencukupi untuk tanggal yang dipilih (tersisa {$free})."],
                    ]);
                }
            }

            $days = max((int) ceil($startDate->diffInDays($endDate)) + 1, 1);

            $booking = Booking::create([
                'user_id' => $user->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => Booking::STATUS_PENDING,
                'total_amount' => 0,
            ]);

            foreach ($items as $item) {
                $equipment = $equipments[$item['equipment_id']];

                $subtotal = $equipment->price_per_day * $item['quantity'] * $days;

                BookingItem::create([
                    'booking_id' => $booking->id,
                    'equipment_id' => $equipment->id,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ]);
            }

            $booking->total_amount = (float) $booking->items()->sum('subtotal');
            $booking->save();

            return $booking;
        });
    }

    /**
     * Admin mengonfirmasi penyerahan alat: stock dikurangi.
     * Transaction + row locking agar tidak terjadi race condition pada stok.
     */
    public function confirmPickup(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->status !== Booking::STATUS_PAID) {
                throw ValidationException::withMessages([
                    'booking' => ['Hanya booking berstatus paid yang dapat dikonfirmasi.'],
                ]);
            }

            $items = $booking->items()->get();

            $equipments = Equipment::query()
                ->whereIn('id', $items->pluck('equipment_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $equipment = $equipments[$item->equipment_id];

                if ($equipment->stock < $item->quantity) {
                    throw ValidationException::withMessages([
                        'booking' => ["Stok {$equipment->name} tidak mencukupi (tersedia {$equipment->stock}, dibutuhkan {$item->quantity})."],
                    ]);
                }
            }

            foreach ($items as $item) {
                $equipments[$item->equipment_id]->decrement('stock', $item->quantity);
            }

            $booking->update(['status' => Booking::STATUS_CONFIRMED]);

            return $booking;
        });
    }

    /**
     * Admin mengonfirmasi pengembalian alat: stock ditambah kembali.
     */
    public function processReturn(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->status !== Booking::STATUS_CONFIRMED) {
                throw ValidationException::withMessages([
                    'booking' => ['Hanya booking berstatus confirmed yang dapat diproses pengembaliannya.'],
                ]);
            }

            $items = $booking->items()->get();

            Equipment::query()
                ->whereIn('id', $items->pluck('equipment_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(fn (Equipment $equipment) => $equipment->increment(
                    'stock',
                    (int) $items->firstWhere('equipment_id', $equipment->id)->quantity,
                ));

            $booking->update(['status' => Booking::STATUS_COMPLETED]);

            return $booking;
        });
    }
}
