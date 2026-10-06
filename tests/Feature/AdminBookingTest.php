<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pelanggan;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->pelanggan = User::factory()->pelanggan()->create();
        $this->equipment = Equipment::factory()->create(['stock' => 5, 'price_per_day' => 80000]);
    }

    public function test_admin_melihat_seluruh_booking_dengan_filter_status(): void
    {
        Booking::factory()->for($this->pelanggan)->count(2)->create();
        $paid = Booking::factory()->for($this->pelanggan)->paid()->create();

        $this->actingAs($this->admin)->getJson('/api/admin/bookings')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->actingAs($this->admin)->getJson('/api/admin/bookings?status=paid')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $paid->id);
    }

    public function test_daftar_booking_admin_menyertakan_nama_dan_nomor_hp_pengguna(): void
    {
        $this->pelanggan->update(['phone_number' => '089876543210']);
        Booking::factory()->for($this->pelanggan)->create();

        $this->actingAs($this->admin)->getJson('/api/admin/bookings')
            ->assertOk()
            ->assertJsonPath('data.0.user.name', $this->pelanggan->name)
            ->assertJsonPath('data.0.user.phone_number', '089876543210');
    }

    public function test_confirm_mengurangi_stok_dan_mengubah_status(): void
    {
        $booking = $this->buatBookingPaid(quantity: 2);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$booking->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_CONFIRMED);

        $this->assertDatabaseHas('equipment', [
            'id' => $this->equipment->id,
            'stock' => 3,
        ]);
    }

    public function test_return_menambah_kembali_stok(): void
    {
        $booking = $this->buatBookingPaid(quantity: 2);
        $this->konfirmasi($booking);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$booking->id}/return")
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_COMPLETED);

        $this->assertDatabaseHas('equipment', [
            'id' => $this->equipment->id,
            'stock' => 5,
        ]);
    }

    public function test_confirm_ditolak_jika_status_bukan_paid(): void
    {
        $booking = Booking::factory()->for($this->pelanggan)->create();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$booking->id}/confirm")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking']);
    }

    public function test_return_ditolak_jika_status_bukan_confirmed(): void
    {
        $booking = $this->buatBookingPaid();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$booking->id}/return")
            ->assertUnprocessable();
    }

    public function test_confirm_gagal_jika_stok_fisik_tidak_mencukupi(): void
    {
        $booking = $this->buatBookingPaid(quantity: 2);
        $this->equipment->update(['stock' => 1]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$booking->id}/confirm")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['booking']);

        // stok tidak berubah karena transaction rollback
        $this->assertDatabaseHas('equipment', ['id' => $this->equipment->id, 'stock' => 1]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => Booking::STATUS_PAID]);
    }

    public function test_pelanggan_ditolak_akses_endpoint_admin_booking(): void
    {
        $this->actingAs($this->pelanggan)
            ->getJson('/api/admin/bookings')
            ->assertForbidden();
    }

    /**
     * Uji konkurensi sederhana: dua booking paid qty 1 dengan stok tersisa 1,
     * dikonfirmasi berurutan — hanya satu yang boleh berhasil dan stock akhir tepat 0.
     */
    public function test_tidak_ada_double_allocation_pada_stok_sisa_satu(): void
    {
        $bookingA = $this->buatBookingPaid(quantity: 1);
        $bookingB = $this->buatBookingPaid(quantity: 1);
        $this->equipment->update(['stock' => 1]);

        $hasilA = $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$bookingA->id}/confirm")->status();
        $hasilB = $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$bookingB->id}/confirm")->status();

        $berhasil = array_filter([$hasilA, $hasilB], fn ($code) => $code === 200);
        $gagal = array_filter([$hasilA, $hasilB], fn ($code) => $code === 422);

        $this->assertCount(1, $berhasil);
        $this->assertCount(1, $gagal);
        $this->assertDatabaseHas('equipment', ['id' => $this->equipment->id, 'stock' => 0]);
    }

    private function buatBookingPaid(int $quantity = 1): Booking
    {
        $booking = Booking::factory()->for($this->pelanggan)->paid()->create([
            'start_date' => today()->addDays(10)->toDateString(),
            'end_date' => today()->addDays(11)->toDateString(),
            'total_amount' => 80000 * $quantity,
        ]);

        $booking->items()->create([
            'equipment_id' => $this->equipment->id,
            'quantity' => $quantity,
            'subtotal' => 80000 * $quantity,
        ]);

        return $booking;
    }

    private function konfirmasi(Booking $booking): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$booking->id}/confirm")
            ->assertOk();
    }
}
