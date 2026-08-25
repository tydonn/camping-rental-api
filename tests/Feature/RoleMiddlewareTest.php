<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_ROUTE = '/api/test/admin-only';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'role:admin'])
            ->get(self::TEST_ROUTE, fn () => response()->json(['message' => 'ok']));
    }

    public function test_admin_bisa_mengakses_route_admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->getJson(self::TEST_ROUTE)->assertOk()->assertJson(['message' => 'ok']);
    }

    public function test_pelanggan_ditolak_pada_route_admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PELANGGAN]));

        $this->getJson(self::TEST_ROUTE)->assertForbidden();
    }

    public function test_tamu_ditolak_pada_route_admin(): void
    {
        $this->getJson(self::TEST_ROUTE)->assertUnauthorized();
    }
}
