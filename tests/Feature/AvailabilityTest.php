<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->equipment = Equipment::factory()->create(['stock' => 5]);
    }

    /**
     * Tanggal relatif terhadap hari ini agar test tidak kedaluwarsa.
     */
    private function tanggal(int $offset): string
    {
        return today()->addDays($offset)->toDateString();
    }

    public function test_menghitung_stok_terpakai_dari_booking_aktif_yang_beririsan(): void
    {
        $this->seedBooking(Booking::STATUS_PENDING, $this->tanggal(10), $this->tanggal(12), 2);
        $this->seedBooking(Booking::STATUS_PAID, $this->tanggal(11), $this->tanggal(13), 1);

        $this->getJson("{$this->endpoint()}?start_date={$this->tanggal(10)}&end_date={$this->tanggal(12)}")
            ->assertOk()
            ->assertJsonPath('data.stock', 5)
            ->assertJsonPath('data.booked', 3)
            ->assertJsonPath('data.available', 2)
            ->assertJsonPath('data.is_available', true);
    }

    public function test_booking_selesai_dibatalkan_tidak_dihitung(): void
    {
        $this->seedBooking(Booking::STATUS_COMPLETED, $this->tanggal(10), $this->tanggal(12), 4);
        $this->seedBooking(Booking::STATUS_CANCELLED, $this->tanggal(10), $this->tanggal(12), 5);

        $this->getJson("{$this->endpoint()}?start_date={$this->tanggal(10)}&end_date={$this->tanggal(12)}")
            ->assertOk()
            ->assertJsonPath('data.booked', 0)
            ->assertJsonPath('data.available', 5);
    }

    public function test_rentang_yang_menyentuh_ujung_tanggal_di_hitap_overlap(): void
    {
        $this->seedBooking(Booking::STATUS_CONFIRMED, $this->tanggal(10), $this->tanggal(12), 1);

        $this->getJson("{$this->endpoint()}?start_date={$this->tanggal(12)}&end_date={$this->tanggal(14)}")
            ->assertOk()
            ->assertJsonPath('data.booked', 1);
    }

    public function test_is_available_false_jika_quantity_diminta_melebihi_sisa(): void
    {
        $this->seedBooking(Booking::STATUS_PENDING, $this->tanggal(10), $this->tanggal(12), 4);

        $this->getJson("{$this->endpoint()}?start_date={$this->tanggal(10)}&end_date={$this->tanggal(12)}&quantity=3")
            ->assertOk()
            ->assertJsonPath('data.available', 1)
            ->assertJsonPath('data.is_available', false);
    }

    public function test_validasi_tanggal_tidak_valid(): void
    {
        $this->getJson("{$this->endpoint()}?start_date={$this->tanggal(12)}&end_date={$this->tanggal(10)}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        $this->getJson("{$this->endpoint()}?start_date=2000-01-01&end_date=2000-01-05")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);
    }

    private function endpoint(): string
    {
        return "/api/equipment/{$this->equipment->id}/availability";
    }

    private function seedBooking(string $status, string $start, string $end, int $quantity): BookingItem
    {
        $booking = Booking::factory()->create([
            'status' => $status,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        return BookingItem::factory()->create([
            'booking_id' => $booking->id,
            'equipment_id' => $this->equipment->id,
            'quantity' => $quantity,
            'subtotal' => $this->equipment->price_per_day * $quantity * 3,
        ]);
    }
}
