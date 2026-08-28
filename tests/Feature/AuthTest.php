<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_pelanggan_dapat_registrasi_dan_mendapat_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'phone_number' => '081234567890',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'budi@example.com')
            ->assertJsonPath('data.phone_number', '081234567890')
            ->assertJsonPath('data.role', User::ROLE_PELANGGAN)
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', [
            'email' => 'budi@example.com',
            'phone_number' => '081234567890',
        ]);
    }

    public function test_registrasi_gagal_jika_validasi_tidak_terpenuhi(): void
    {
        $this->postJson('/api/register', [
            'name' => '',
            'email' => 'bukan-email',
            'phone_number' => 'nomor-salah!',
            'password' => '123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'phone_number', 'password']);
    }

    public function test_user_dapat_login_dan_mendapat_token(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'rahasia123',
        ])->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonStructure(['token']);
    }

    public function test_login_gagal_dengan_kredensial_salah(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password-salah',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_user_login_bisa_lihat_profil_sendiri(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_me_mengembalikan_401_tanpa_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_logout_menghapus_token_aktif(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token');

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }
}
