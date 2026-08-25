<?php

namespace App\Services;

use App\Models\BookingItem;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Builder;

class AvailabilityService
{
    /**
     * Total quantity alat yang sudah dipesan pada rentang tanggal,
     * hanya dari booking berstatus aktif (pending/paid/confirmed).
     * Rentang tanggal dianggap inklusif (overlap jika saling menyentuh).
     */
    public function bookedQuantity(int $equipmentId, string $startDate, string $endDate): int
    {
        return (int) BookingItem::query()
            ->where('equipment_id', $equipmentId)
            ->whereHas('booking', fn (Builder $booking) => $booking
                ->active()
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate))
            ->sum('quantity');
    }

    public function availableQuantity(Equipment $equipment, string $startDate, string $endDate): int
    {
        return max($equipment->stock - $this->bookedQuantity($equipment->id, $startDate, $endDate), 0);
    }
}
