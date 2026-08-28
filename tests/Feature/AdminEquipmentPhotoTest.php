<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminEquipmentPhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->admin()->create();
        $this->category = Category::factory()->create();
    }

    private function fakePhoto(string $name = 'foto.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            (string) file_get_contents(__DIR__.'/fixtures/photo.png'),
        );
    }

    public function test_admin_bisa_upload_foto_saat_membuat_alat(): void
    {
        $this->actingAs($this->admin)
            ->post('/api/admin/equipment', [
                'category_id' => $this->category->id,
                'name' => 'Tenda Dome',
                'description' => 'Tenda kapasitas 4 orang',
                'price_per_day' => 150000,
                'stock' => 5,
                'photo' => $this->fakePhoto('tenda.png'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Tenda Dome');

        $equipment = Equipment::query()->where('name', 'Tenda Dome')->firstOrFail();

        $this->assertNotNull($equipment->photo);
        Storage::disk('public')->assertExists($equipment->photo);
        $this->assertStringStartsWith('equipment/', $equipment->photo);
    }

    public function test_admin_bisa_mengganti_foto_dan_file_lama_terhapus(): void
    {
        $pathLama = $this->fakePhoto('lama.png')->store('equipment', 'public');
        $equipment = Equipment::factory()->create([
            'category_id' => $this->category->id,
            'photo' => $pathLama,
        ]);

        $this->actingAs($this->admin)
            ->post("/api/admin/equipment/{$equipment->id}", [
                '_method' => 'PUT',
                'photo' => $this->fakePhoto('baru.png'),
            ])
            ->assertOk()
            ->assertJsonPath('data.photo', fn (string $photo) => $photo !== $pathLama);

        $equipment->refresh();

        Storage::disk('public')->assertExists($equipment->photo);
        Storage::disk('public')->assertMissing($pathLama);
    }

    public function test_admin_bisa_menghapus_foto_tanpa_mengganti(): void
    {
        $pathLama = $this->fakePhoto('lama.png')->store('equipment', 'public');
        $equipment = Equipment::factory()->create([
            'category_id' => $this->category->id,
            'photo' => $pathLama,
        ]);

        $this->actingAs($this->admin)
            ->post("/api/admin/equipment/{$equipment->id}", [
                '_method' => 'PUT',
                'remove_photo' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.photo', null);

        Storage::disk('public')->assertMissing($pathLama);
        $this->assertNull($equipment->refresh()->photo);
    }

    public function test_update_tanpa_foto_baru_tidak_mengubah_foto_lama(): void
    {
        $pathLama = $this->fakePhoto('lama.png')->store('equipment', 'public');
        $equipment = Equipment::factory()->create([
            'category_id' => $this->category->id,
            'photo' => $pathLama,
        ]);

        $this->actingAs($this->admin)
            ->post("/api/admin/equipment/{$equipment->id}", [
                '_method' => 'PUT',
                'name' => 'Nama Baru',
            ])
            ->assertOk()
            ->assertJsonPath('data.photo', $pathLama);

        Storage::disk('public')->assertExists($pathLama);
    }

    public function test_format_foto_yang_tidak_didukung_ditolak_validasi(): void
    {
        $this->actingAs($this->admin)
            ->post('/api/admin/equipment', [
                'category_id' => $this->category->id,
                'name' => 'Tenda Dome',
                'price_per_day' => 150000,
                'stock' => 5,
                'photo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_ukuran_foto_lebih_dari_2mb_ditolak_validasi(): void
    {
        $this->actingAs($this->admin)
            ->post('/api/admin/equipment', [
                'category_id' => $this->category->id,
                'name' => 'Tenda Dome',
                'price_per_day' => 150000,
                'stock' => 5,
                'photo' => $this->fakePhotoContent(str_repeat(
                    (string) file_get_contents(__DIR__.'/fixtures/photo.png'),
                    32000,
                )),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    private function fakePhotoContent(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('gede.png', $content);
    }

    public function test_menghapus_alat_juga_menghapus_file_foto(): void
    {
        $path = $this->fakePhoto('foto.png')->store('equipment', 'public');
        $equipment = Equipment::factory()->create([
            'category_id' => $this->category->id,
            'photo' => $path,
        ]);

        $this->actingAs($this->admin)
            ->delete("/api/admin/equipment/{$equipment->id}")
            ->assertOk();

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('equipment', ['id' => $equipment->id]);
    }
}
