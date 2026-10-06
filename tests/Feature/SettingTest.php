<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->admin()->create();
        $this->pelanggan = User::factory()->pelanggan()->create();
    }

    private function fakePhoto(string $name = 'hero.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            (string) file_get_contents(__DIR__.'/fixtures/photo.png'),
        );
    }

    public function test_publik_bisa_melihat_settings_dengan_hero_image_kosong(): void
    {
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.hero_image', null);
    }

    public function test_admin_bisa_upload_gambar_hero(): void
    {
        $this->actingAs($this->admin)
            ->post('/api/admin/settings/hero-image', [
                'photo' => $this->fakePhoto(),
            ])
            ->assertOk()
            ->assertJsonPath('data.hero_image', fn (?string $path) => $path !== null);

        $path = SiteSetting::get(SiteSetting::KEY_HERO_IMAGE);

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('hero/', $path);
    }

    public function test_mengganti_hero_image_menghapus_file_lama(): void
    {
        $pathLama = $this->fakePhoto('lama.png')->store('hero', 'public');
        SiteSetting::set(SiteSetting::KEY_HERO_IMAGE, $pathLama);

        $response = $this->actingAs($this->admin)
            ->post('/api/admin/settings/hero-image', [
                'photo' => $this->fakePhoto('baru.png'),
            ])
            ->assertOk();

        $pathBaru = $response->json('data.hero_image');

        $this->assertNotSame($pathLama, $pathBaru);
        Storage::disk('public')->assertExists($pathBaru);
        Storage::disk('public')->assertMissing($pathLama);
    }

    public function test_admin_bisa_menghapus_gambar_hero(): void
    {
        $path = $this->fakePhoto()->store('hero', 'public');
        SiteSetting::set(SiteSetting::KEY_HERO_IMAGE, $path);

        $this->actingAs($this->admin)
            ->delete('/api/admin/settings/hero-image')
            ->assertOk()
            ->assertJsonPath('data.hero_image', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull(SiteSetting::get(SiteSetting::KEY_HERO_IMAGE));
    }

    public function test_non_admin_tidak_bisa_upload_hero_image(): void
    {
        $this->actingAs($this->pelanggan)
            ->post('/api/admin/settings/hero-image', [
                'photo' => $this->fakePhoto(),
            ])
            ->assertForbidden();
    }

    public function test_tamu_tidak_bisa_upload_hero_image(): void
    {
        $this->post('/api/admin/settings/hero-image', [
            'photo' => $this->fakePhoto(),
        ])->assertUnauthorized();
    }

    public function test_upload_hero_tanpa_file_ditolak_validasi(): void
    {
        $this->actingAs($this->admin)
            ->post('/api/admin/settings/hero-image', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_upload_hero_format_tidak_didukung_ditolak_validasi(): void
    {
        $this->actingAs($this->admin)
            ->post('/api/admin/settings/hero-image', [
                'photo' => UploadedFile::fake()->create('animasi.gif', 500, 'image/gif'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_publik_bisa_melihat_maps_query_kosong(): void
    {
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.maps_query', null);
    }

    public function test_admin_bisa_mengatur_maps_query(): void
    {
        $this->actingAs($this->admin)
            ->post('/api/admin/settings/maps-query', [
                'query' => 'Jl. Raya Lembang No. 88, Bandung Barat',
            ])
            ->assertOk()
            ->assertJsonPath('data.maps_query', 'Jl. Raya Lembang No. 88, Bandung Barat');

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.maps_query', 'Jl. Raya Lembang No. 88, Bandung Barat');
    }

    public function test_admin_bisa_reset_maps_query(): void
    {
        SiteSetting::set(SiteSetting::KEY_MAPS_QUERY, 'Jl. Raya Lembang No. 88');

        $this->actingAs($this->admin)
            ->delete('/api/admin/settings/maps-query')
            ->assertOk()
            ->assertJsonPath('data.maps_query', null);

        $this->assertNull(SiteSetting::get(SiteSetting::KEY_MAPS_QUERY));
    }

    public function test_maps_query_tanpa_nilai_ditolak_validasi(): void
    {
        $this->actingAs($this->admin)
            ->post('/api/admin/settings/maps-query', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['query']);
    }

    public function test_non_admin_tidak_bisa_mengatur_maps_query(): void
    {
        $this->actingAs($this->pelanggan)
            ->post('/api/admin/settings/maps-query', [
                'query' => 'Alamat sembarangan',
            ])
            ->assertForbidden();
    }

    public function test_publik_melihat_data_kontak_kosong_oleh_default(): void
    {
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.address', null)
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.hours', null);
    }

    public function test_admin_bisa_mengatur_data_kontak(): void
    {
        SiteSetting::set(SiteSetting::KEY_HERO_IMAGE, 'hero/lama.png');
        SiteSetting::set(SiteSetting::KEY_MAPS_QUERY, 'Berkah Alam Outdoor Organizer');

        $this->actingAs($this->admin)
            ->postJson('/api/admin/settings/contact', [
                'address' => 'Jl. Raya Lembang No. 88, Lembang, Kabupaten Bandung Barat, Jawa Barat 40391',
                'phone' => '+62 812-3456-7890',
                'email' => 'halo@contoh.id',
                'hours' => 'Setiap hari, 08.00 - 20.00 WIB',
            ])
            ->assertOk()
            ->assertJsonPath('data.address', 'Jl. Raya Lembang No. 88, Lembang, Kabupaten Bandung Barat, Jawa Barat 40391')
            ->assertJsonPath('data.phone', '+62 812-3456-7890')
            ->assertJsonPath('data.email', 'halo@contoh.id')
            ->assertJsonPath('data.hours', 'Setiap hari, 08.00 - 20.00 WIB')
            ->assertJsonPath('data.hero_image', 'hero/lama.png')
            ->assertJsonPath('data.maps_query', 'Berkah Alam Outdoor Organizer');

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.email', 'halo@contoh.id');

        $this->assertDatabaseHas('site_settings', [
            'key' => SiteSetting::KEY_CONTACT,
            'address' => 'Jl. Raya Lembang No. 88, Lembang, Kabupaten Bandung Barat, Jawa Barat 40391',
            'phone' => '+62 812-3456-7890',
            'email' => 'halo@contoh.id',
            'hours' => 'Setiap hari, 08.00 - 20.00 WIB',
        ]);
    }

    public function test_update_kontak_hanya_mengubah_field_yang_dikirim(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/settings/contact', [
                'address' => 'Alamat Lama',
                'phone' => '081234567890',
                'email' => 'lama@contoh.id',
                'hours' => 'Setiap hari, 09.00 - 17.00 WIB',
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson('/api/admin/settings/contact', ['phone' => '081298765432'])
            ->assertOk()
            ->assertJsonPath('data.phone', '081298765432')
            ->assertJsonPath('data.address', 'Alamat Lama')
            ->assertJsonPath('data.email', 'lama@contoh.id')
            ->assertJsonPath('data.hours', 'Setiap hari, 09.00 - 17.00 WIB');
    }

    public function test_validasi_data_kontak_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/settings/contact', [
                'address' => str_repeat('a', 256),
                'phone' => 'telepon!',
                'email' => 'bukan-email',
                'hours' => str_repeat('b', 121),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address', 'phone', 'email', 'hours']);
    }

    public function test_admin_bisa_mengosongkan_data_kontak(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/settings/contact', [
                'address' => 'Alamat Lama',
                'phone' => '081234567890',
                'email' => 'lama@contoh.id',
                'hours' => 'Setiap hari, 09.00 - 17.00 WIB',
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->deleteJson('/api/admin/settings/contact')
            ->assertOk()
            ->assertJsonPath('data.address', null)
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.hours', null);

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.address', null);
    }

    public function test_non_admin_tidak_bisa_mengatur_data_kontak(): void
    {
        $this->actingAs($this->pelanggan)
            ->postJson('/api/admin/settings/contact', ['phone' => '081234567890'])
            ->assertForbidden();

        $this->actingAs($this->pelanggan)
            ->deleteJson('/api/admin/settings/contact')
            ->assertForbidden();
    }

    public function test_tamu_tidak_bisa_mengatur_data_kontak(): void
    {
        $this->postJson('/api/admin/settings/contact', ['phone' => '081234567890'])
            ->assertUnauthorized();

        $this->deleteJson('/api/admin/settings/contact')
            ->assertUnauthorized();
    }
}
