<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResidentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // Data wilayah minimal. Jika insert gagal, cek nama kolom tabel laravolt
        DB::table('indonesia_provinces')->insert(['code' => '32', 'name' => 'Jawa Barat']);
        DB::table('indonesia_cities')->insert(['code' => '3204', 'province_code' => '32', 'name' => 'Kab. Bandung']);
        DB::table('indonesia_districts')->insert(['code' => '320401', 'city_code' => '3204', 'name' => 'Kec. Contoh']);
        DB::table('indonesia_villages')->insert(['code' => '3204012001', 'district_code' => '320401', 'name' => 'Kel. Contoh']);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'nik'          => '3204010101900001',
            'name'         => 'Budi Santoso',
            'gender'       => 'male',
            'birth_date'   => '1985-03-14',
            'street_name'  => 'Jl. Melati',
            'house_number' => '12',
            'rt'           => '001',
            'rw'           => '002',
            'postal_code'  => '40391',
            'village_code' => '3204012001',
            'phone'        => '0812-3456-7890',
            'email'        => 'budi@email.com',
        ], $override);
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/v1/residents')->assertUnauthorized();
    }

    public function test_admin_creates_resident_with_normalized_phone_and_derived_region(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/residents', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.phone', '6281234567890')
            ->assertJsonPath('data.nik_masked', '3204********0001')
            ->assertJsonPath('data.province_code', '32')
            ->assertJsonPath('data.city_code', '3204')
            ->assertJsonPath('data.district_code', '320401')
            ->assertJsonPath('data.region.village_name', 'Kel. Contoh')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissingPath('data.nik');

        // NIK tidak tersimpan sebagai teks biasa
        $this->assertDatabaseMissing('residents', ['nik' => '3204010101900001']);
    }

    public function test_duplicate_nik_is_rejected(): void
    {
        $this->actingAsRole('admin');
        $this->postJson('/api/v1/residents', $this->payload())->assertCreated();

        $this->postJson('/api/v1/residents', $this->payload(['name' => 'Orang Lain']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nik');
    }

    public function test_invalid_village_and_phone_are_rejected(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/residents', $this->payload(['village_code' => '9999999999', 'phone' => '123']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['village_code', 'phone']);
    }

    public function test_update_can_keep_own_nik(): void
    {
        $this->actingAsRole('admin');
        $id = $this->postJson('/api/v1/residents', $this->payload())->json('data.id');

        $this->putJson("/api/v1/residents/{$id}", $this->payload(['name' => 'Budi Baru']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Budi Baru');
    }

    public function test_search_by_name_nik_and_phone(): void
    {
        $this->actingAsRole('admin');
        $this->postJson('/api/v1/residents', $this->payload())->assertCreated();
        Resident::factory()->create(['name' => 'Siti Rahayu']);

        $this->getJson('/api/v1/residents?search=budi')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/residents?search=3204010101900001')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/residents?search=0812-3456')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/residents')->assertJsonCount(2, 'data');
    }

    public function test_permissions(): void
    {
        $this->actingAsRole('bendahara');
        $this->getJson('/api/v1/residents')->assertOk();
        $this->postJson('/api/v1/residents', $this->payload())->assertForbidden();

        $this->actingAsRole('meter_reader');
        $this->getJson('/api/v1/residents')->assertForbidden();
    }

    public function test_reveal_nik_returns_full_nik(): void
    {
        $this->actingAsRole('admin');
        $id = $this->postJson('/api/v1/residents', $this->payload())->json('data.id');

        $this->getJson("/api/v1/residents/{$id}/nik")
            ->assertOk()
            ->assertJsonPath('data.nik', '3204010101900001');
    }

    public function test_deactivate_resident(): void
    {
        $this->actingAsRole('admin');
        $id = $this->postJson('/api/v1/residents', $this->payload())->json('data.id');

        $this->patchJson("/api/v1/residents/{$id}/active", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->getJson('/api/v1/residents?is_active=1')->assertJsonCount(0, 'data');
    }

    public function test_photo_upload_download_and_remove(): void
    {
        Storage::fake('local');
        config(['vib.private_disk' => 'local']);

        $this->actingAsRole('admin');
        $id = $this->postJson('/api/v1/residents', $this->payload())->json('data.id');

        $this->post("/api/v1/residents/{$id}/photo", ['photo' => UploadedFile::fake()->image('foto.jpg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.has_photo', true);

        $path = Resident::find($id)->photo_path;
        Storage::disk('local')->assertExists($path);

        $this->get("/api/v1/residents/{$id}/photo")->assertOk();

        $this->deleteJson("/api/v1/residents/{$id}/photo")->assertOk()->assertJsonPath('data.has_photo', false);
        Storage::disk('local')->assertMissing($path);
    }
}