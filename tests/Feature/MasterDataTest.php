<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public static function endpoints(): array
    {
        return [
            'blocks'            => ['blocks'],
            'streets'           => ['streets'],
            'zones'             => ['zones'],
            'villages'          => ['villages'],
            'work-areas'        => ['work-areas'],
            'banks'             => ['banks'],
            'chart-of-accounts' => ['chart-of-accounts'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    #[DataProvider('endpoints')]
    public function test_guest_is_rejected(string $path): void
    {
        $this->getJson("/api/v1/master/{$path}")->assertUnauthorized();
    }

    #[DataProvider('endpoints')]
    public function test_admin_can_create_and_code_is_uppercased(string $path): void
    {
        $this->actingAsRole('admin');

        $this->postJson("/api/v1/master/{$path}", ['code' => ' tst-01 ', 'name' => 'Contoh'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'TST-01')
            ->assertJsonPath('data.is_active', true);
    }

    #[DataProvider('endpoints')]
    public function test_duplicate_code_is_rejected(string $path): void
    {
        $this->actingAsRole('admin');
        $this->postJson("/api/v1/master/{$path}", ['code' => 'DUP', 'name' => 'Satu'])->assertCreated();

        $this->postJson("/api/v1/master/{$path}", ['code' => 'dup', 'name' => 'Dua'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    #[DataProvider('endpoints')]
    public function test_reader_can_read_but_not_write(string $path): void
    {
        $this->actingAsRole('meter_reader');

        $this->getJson("/api/v1/master/{$path}")->assertOk();
        $this->postJson("/api/v1/master/{$path}", ['code' => 'X1', 'name' => 'X'])->assertForbidden();
    }

    #[DataProvider('endpoints')]
    public function test_update_filter_and_delete(string $path): void
    {
        $this->actingAsRole('admin');

        $id = $this->postJson("/api/v1/master/{$path}", ['code' => 'A1', 'name' => 'Awal'])
            ->json('data.id');
        $this->postJson("/api/v1/master/{$path}", ['code' => 'B1', 'name' => 'Lain'])->assertCreated();

        // Update dan nonaktifkan. Kode sendiri tidak dianggap duplikat
        $this->putJson("/api/v1/master/{$path}/{$id}", ['code' => 'A1', 'name' => 'Baru', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Baru')
            ->assertJsonPath('data.is_active', false);

        $this->getJson("/api/v1/master/{$path}?is_active=1")->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/master/{$path}?search=lain")->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/master/{$path}?all=1")->assertJsonCount(2, 'data');

        $this->deleteJson("/api/v1/master/{$path}/{$id}")->assertNoContent();
        $this->getJson("/api/v1/master/{$path}/{$id}")->assertNotFound();
    }

    public function test_created_by_is_recorded(): void
    {
        $admin = $this->actingAsRole('admin');

        $this->postJson('/api/v1/master/blocks', ['code' => 'Z', 'name' => 'Blok Z'])->assertCreated();

        $this->assertDatabaseHas('blocks', ['code' => 'Z', 'created_by' => $admin->id]);
    }
}