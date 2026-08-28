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
}
