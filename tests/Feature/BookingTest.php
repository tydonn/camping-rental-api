<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $pelanggan;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelanggan = User::factory()->pelanggan()->create();
        $this->equipment = Equipment::factory()->create([
            'price_per_day' => 80000,
            'stock' => 5,
        ]);
    }

    public function test_pelanggan_dapat_membuat_booking_dengan_snapshot_subtotal(): void
    {
        $response = $this->actingAs($this->pelanggan)->postJson('/api/bookings', [
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'items' => [
                ['equipment_id' => $this->equipment->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', Booking::STATUS_PENDING)
            ->assertJsonPath('data.duration_days', 3)
            ->assertJsonPath('data.total_amount', '480000.00')
            ->assertJsonPath('data.items.0.subtotal', '480000.00');

        $this->assertDatabaseHas('booking_items', [
            'equipment_id' => $this->equipment->id,
            'quantity' => 2,
            'subtotal' => 480000,
        ]);
    }

    public function test_snapshot_subtotal_tidak_berubah_meski_harga_master_naik(): void
    {
        $bookingId = $this->actingAs($this->pelanggan)->postJson('/api/bookings', [
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11',
            'items' => [['equipment_id' => $this->equipment->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('bookings', ['id' => $bookingId, 'total_amount' => 160000]);

        $this->equipment->update(['price_per_day' => 150000]);

        $this->getJson("/api/bookings/{$bookingId}")
            ->assertOk()
            ->assertJsonPath('data.total_amount', '160000.00')
            ->assertJsonPath('data.items.0.equipment.price_per_day', '150000.00');
    }

    public function test_booking_gagal_jika_stok_tidak_mencukupi(): void
    {
        Equipment::factory()->create(['stock' => 2]);

        $this->actingAs($this->pelanggan)
            ->postJson('/api/bookings', [
                'start_date' => '2026-09-10',
                'end_date' => '2026-09-12',
                'items' => [['equipment_id' => $this->equipment->id, 'quantity' => 10]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_menghitung_stok_terpakai_rentang_beririsan(): void
    {
        // stok 5; booking aktif lain sudah memakai 4 pada rentang yang sama
        $existing = Booking::factory()->create([
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
        ]);
        $existing->items()->create([
            'equipment_id' => $this->equipment->id,
            'quantity' => 4,
            'subtotal' => 320000,
        ]);

        $this->actingAs($this->pelanggan)
            ->postJson('/api/bookings', [
                'start_date' => '2026-09-11',
                'end_date' => '2026-09-13',
                'items' => [['equipment_id' => $this->equipment->id, 'quantity' => 3]],
            ])
            ->assertUnprocessable();
    }

    public function test_booking_gagal_jika_item_duplikat_atau_kosong(): void
    {
        $this->actingAs($this->pelanggan)
            ->postJson('/api/bookings', [
                'start_date' => '2026-09-10',
                'end_date' => '2026-09-12',
                'items' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->actingAs($this->pelanggan)
            ->postJson('/api/bookings', [
                'start_date' => '2026-09-10',
                'end_date' => '2026-09-12',
                'items' => [
                    ['equipment_id' => $this->equipment->id, 'quantity' => 1],
                    ['equipment_id' => $this->equipment->id, 'quantity' => 2],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.equipment_id']);
    }

    public function test_riwayat_booking_hanya_milik_user_yang_login(): void
    {
        $milik = Booking::factory()->for($this->pelanggan)->create();
        Booking::factory()->count(2)->create();

        $this->actingAs($this->pelanggan)->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $milik->id);
    }

    public function test_detail_booking_user_lain_tidak_dapat_diakses(): void
    {
        $booking = Booking::factory()->create();

        $this->actingAs($this->pelanggan)
            ->getJson("/api/bookings/{$booking->id}")
            ->assertNotFound();
    }
}
