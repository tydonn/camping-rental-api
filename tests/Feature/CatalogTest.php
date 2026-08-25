<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Equipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_publik_dapat_melihat_daftar_katalog(): void
    {
        Equipment::factory()->count(3)->create();

        $this->getJson('/api/equipment')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'price_per_day', 'stock', 'category' => ['id', 'name']]],
                'links',
                'meta',
            ]);
    }

    public function test_katalog_bisa_difilter_berdasarkan_pencarian_dan_kategori(): void
    {
        $tenda = Category::factory()->create(['name' => 'Tenda']);
        $kompor = Category::factory()->create(['name' => 'Kompor']);

        Equipment::factory()->create(['category_id' => $tenda->id, 'name' => 'Tenda Dome']);
        Equipment::factory()->create(['category_id' => $tenda->id, 'name' => 'Tenda Tunnel']);
        Equipment::factory()->create(['category_id' => $kompor->id, 'name' => 'Kompor Portable']);

        $this->getJson('/api/equipment?search=Tenda')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/equipment?category_id={$kompor->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kompor Portable');
    }

    public function test_publik_dapat_melihat_detail_alat(): void
    {
        $equipment = Equipment::factory()->create();

        $this->getJson("/api/equipment/{$equipment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $equipment->id)
            ->assertJsonPath('data.category.name', $equipment->category->name);
    }

    public function test_detail_alat_tidak_ditemukan(): void
    {
        $this->getJson('/api/equipment/999')->assertNotFound();
    }
}
