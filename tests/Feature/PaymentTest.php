<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Equipment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $pelanggan;

    private User $admin;

    private Equipment $equipment;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelanggan = User::factory()->pelanggan()->create();
        $this->admin = User::factory()->admin()->create();
        $this->equipment = Equipment::factory()->create(['price_per_day' => 80000, 'stock' => 5]);
        $this->booking = Booking::factory()->for($this->pelanggan)->create([
            'start_date' => today()->addDays(10)->toDateString(),
            'end_date' => today()->addDays(11)->toDateString(),
            'total_amount' => 160000,
        ]);
        $this->booking->items()->create([
            'equipment_id' => $this->equipment->id,
            'quantity' => 2,
            'subtotal' => 160000,
        ]);
    }

    public function test_pelanggan_dapat_melaporkan_pembayaran(): void
    {
        $this->actingAs($this->pelanggan)
            ->postJson("/api/bookings/{$this->booking->id}/payments", [
                'method' => Payment::METHOD_TRANSFER,
                'note' => 'Transfer BCA a/n Budi',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', Payment::STATUS_PENDING)
            ->assertJsonPath('data.amount', '160000.00')
            ->assertJsonPath('data.booking_id', $this->booking->id);
    }

    public function test_tidak_bisa_melapor_dua_kali_sebelum_diverifikasi(): void
    {
        $this->laporPembayaran();

        $this->actingAs($this->pelanggan)
            ->postJson("/api/bookings/{$this->booking->id}/payments", ['method' => 'cash'])
            ->assertUnprocessable();
    }

    public function test_method_yang_tidak_dikenal_ditolak_validasi(): void
    {
        $this->actingAs($this->pelanggan)
            ->postJson("/api/bookings/{$this->booking->id}/payments", ['method' => 'paypal'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['method']);
    }

    public function test_laporan_pembayaran_booking_user_lain_tidak_ditemukan(): void
    {
        $userLain = User::factory()->pelanggan()->create();

        $this->actingAs($userLain)
            ->postJson("/api/bookings/{$this->booking->id}/payments", ['method' => 'cash'])
            ->assertNotFound();
    }

    public function test_admin_memverifikasi_pembayaran_dan_booking_jadi_paid(): void
    {
        $payment = $this->laporPembayaran();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/payments/{$payment->id}/verify")
            ->assertOk()
            ->assertJsonPath('data.status', Payment::STATUS_VERIFIED);

        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'status' => Booking::STATUS_PAID,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'verified_by' => $this->admin->id,
        ]);
    }

    public function test_admin_menolak_pembayaran_dan_booking_tetap_pending(): void
    {
        $payment = $this->laporPembayaran();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/payments/{$payment->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', Payment::STATUS_REJECTED);

        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'status' => Booking::STATUS_PENDING,
        ]);

        // pelanggan dapat melapor ulang setelah ditolak
        $this->actingAs($this->pelanggan)
            ->postJson("/api/bookings/{$this->booking->id}/payments", ['method' => 'transfer'])
            ->assertCreated();
    }

    public function test_verifikasi_hanya_berlaku_untuk_payment_pending(): void
    {
        $payment = $this->laporPembayaran();
        $payment->update(['status' => Payment::STATUS_VERIFIED]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/payments/{$payment->id}/verify")
            ->assertUnprocessable();
    }

    public function test_daftar_pembayaran_admin_terfilter_status(): void
    {
        Payment::factory()->count(2)->for($this->booking)->sequence(
            ['status' => Payment::STATUS_PENDING],
            ['status' => Payment::STATUS_REJECTED],
        )->create();

        $this->actingAs($this->admin)->getJson('/api/admin/payments?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_pelanggan_ditolak_mengakses_endpoint_admin(): void
    {
        $this->actingAs($this->pelanggan)
            ->getJson('/api/admin/payments')
            ->assertForbidden();
    }

    public function test_booking_response_menyertakan_riwayat_payments(): void
    {
        $this->actingAs($this->pelanggan)
            ->postJson('/api/bookings', [
                'start_date' => today()->addDays(10)->toDateString(),
                'end_date' => today()->addDays(11)->toDateString(),
                'items' => [['equipment_id' => $this->equipment->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.payments', []);
    }

    public function test_status_pembayaran_terlihat_di_detail_booking_setelah_direject(): void
    {
        $payment = $this->laporPembayaran();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/payments/{$payment->id}/reject")
            ->assertOk();

        $this->actingAs($this->pelanggan)->getJson("/api/bookings/{$this->booking->id}")
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_PENDING)
            ->assertJsonPath('data.payments.0.status', Payment::STATUS_REJECTED);
    }

    public function test_status_pembayaran_terlihat_di_daftar_booking_setelah_diverifikasi(): void
    {
        $payment = $this->laporPembayaran();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/payments/{$payment->id}/verify")
            ->assertOk();

        $this->actingAs($this->pelanggan)->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonPath('data.0.status', Booking::STATUS_PAID)
            ->assertJsonPath('data.0.payments.0.status', Payment::STATUS_VERIFIED);
    }

    public function test_lapor_ulang_muncul_sebagai_entri_terbaru_di_riwayat(): void
    {
        $payment = $this->laporPembayaran();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/payments/{$payment->id}/reject")
            ->assertOk();

        $this->actingAs($this->pelanggan)
            ->postJson("/api/bookings/{$this->booking->id}/payments", ['method' => 'qris'])
            ->assertCreated();

        $this->actingAs($this->pelanggan)->getJson("/api/bookings/{$this->booking->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.payments')
            ->assertJsonPath('data.payments.0.method', 'qris')
            ->assertJsonPath('data.payments.1.status', Payment::STATUS_REJECTED);
    }

    private function laporPembayaran(): Payment
    {
        $this->actingAs($this->pelanggan)
            ->postJson("/api/bookings/{$this->booking->id}/payments", [
                'method' => Payment::METHOD_TRANSFER,
            ])
            ->assertCreated();

        return Payment::query()->where('booking_id', $this->booking->id)->sole();
    }
}
