<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_login_and_get_roles(): void
    {
        $user = User::factory()->create(['email' => 'bendahara@vib.test', 'password' => 'Rahasia123']);
        $user->assignRole('bendahara');

        $this->postJson('/api/v1/auth/login', ['email' => 'bendahara@vib.test', 'password' => 'Rahasia123'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'expires_at', 'user' => ['id', 'roles', 'permissions']]])
            ->assertJsonPath('data.user.roles.0', 'bendahara');
    }

    public function test_wrong_password_returns_401(): void
    {
        User::factory()->create(['email' => 'a@vib.test', 'password' => 'Rahasia123']);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@vib.test', 'password' => 'salah'])
            ->assertUnauthorized();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create(['email' => 'b@vib.test', 'password' => 'Rahasia123', 'is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => 'b@vib.test', 'password' => 'Rahasia123'])
            ->assertForbidden();
    }

    public function test_me_requires_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'x@vib.test', 'password' => 'salah']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'x@vib.test', 'password' => 'salah'])
            ->assertStatus(429);
    }

    public function test_logout_revokes_token(): void
    {
        $user  = User::factory()->create(['password' => 'Rahasia123']);
        $token = $user->createToken('tes')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}