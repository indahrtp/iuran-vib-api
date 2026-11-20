<?php

namespace Tests\Feature;

use App\Models\{Block, Resident, Street, Unit, User, Village, Zone};
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitTest extends TestCase
{
    use RefreshDatabase;

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

    private function payload(array $override = []): array
    {
        return array_merge([
            'street_id'             => Street::firstOrCreate(['code' => 'MLT'], ['name' => 'Jl. Melati'])->id,
            'block_id'              => Block::firstOrCreate(['code' => 'A'], ['name' => 'Blok A'])->id,
            'zone_id'               => Zone::firstOrCreate(['code' => 'Z1'], ['name' => 'Zona 1'])->id,
            'village_id'            => Village::firstOrCreate(['code' => 'KEL-01'], ['name' => 'Kelurahan Contoh'])->id,
            'house_number'          => '12',
            'owner_id'              => Resident::factory()->create()->id,
            'fee_guarantor'         => 'owner',
            'water_tariff_category' => 'general',
            'land_area'             => 120,
            'is_vacant_land'        => false,
            'building_area'         => 90,
            'building_status'       => 'occupied',
            'start_date'            => '2020-01-01',
        ], $override);
    }

    public function test_create_unit_records_ownership_history(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/units', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.address', 'Jl. Melati Blok A No. 12')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseCount('unit_ownerships', 1);
        $this->assertDatabaseCount('unit_occupancies', 0);
    }

    public function test_vacant_land_rejects_building_fields(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/units', $this->payload(['is_vacant_land' => true]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['building_area', 'building_status']);

        $this->postJson('/api/v1/units', $this->payload([
            'is_vacant_land' => true, 'building_area' => null, 'building_status' => null,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.building_status', null)
            ->assertJsonPath('data.building_area', null);
    }

    public function test_rental_type_rules(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/units', $this->payload(['building_status' => 'rented']))
            ->assertUnprocessable()->assertJsonValidationErrors('rental_type');

        $this->postJson('/api/v1/units', $this->payload(['rental_type' => 'monthly']))
            ->assertUnprocessable()->assertJsonValidationErrors('rental_type');

        $this->postJson('/api/v1/units', $this->payload(['building_status' => 'rented', 'rental_type' => 'monthly']))
            ->assertCreated()->assertJsonPath('data.rental_type', 'monthly');
    }

    public function test_duplicate_address_is_rejected(): void
    {
        $this->actingAsRole('admin');
        $this->postJson('/api/v1/units', $this->payload())->assertCreated();

        $this->postJson('/api/v1/units', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('house_number');
    }

    public function test_guarantor_must_exist(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/units', $this->payload(['fee_guarantor' => 'occupant']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fee_guarantor');
    }

    public function test_replace_owner_closes_previous_history(): void
    {
        $this->actingAsRole('admin');
        $oldOwner = Resident::factory()->create();
        $newOwner = Resident::factory()->create();

        $id = $this->postJson('/api/v1/units', $this->payload(['owner_id' => $oldOwner->id]))->json('data.id');

        $this->postJson("/api/v1/units/{$id}/owner", ['resident_id' => $oldOwner->id, 'start_date' => '2024-05-01'])
            ->assertUnprocessable();

        $this->postJson("/api/v1/units/{$id}/owner", ['resident_id' => $newOwner->id, 'start_date' => '2019-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');

        $this->postJson("/api/v1/units/{$id}/owner", ['resident_id' => $newOwner->id, 'start_date' => '2024-05-01'])
            ->assertOk()->assertJsonPath('data.owner_id', $newOwner->id);

        $this->assertDatabaseHas('unit_ownerships', ['resident_id' => $oldOwner->id, 'end_date' => '2024-04-30']);
        $this->assertDatabaseHas('unit_ownerships', ['resident_id' => $newOwner->id, 'end_date' => null]);

        $this->getJson("/api/v1/units/{$id}/history")->assertOk()->assertJsonCount(2, 'data.owners');
    }

    public function test_occupant_can_be_cleared_unless_guarantor(): void
    {
        $this->actingAsRole('admin');
        $occupant = Resident::factory()->create();

        $id = $this->postJson('/api/v1/units', $this->payload([
            'occupant_id' => $occupant->id, 'fee_guarantor' => 'occupant',
        ]))->assertCreated()->json('data.id');

        $this->postJson("/api/v1/units/{$id}/occupant", ['resident_id' => null, 'start_date' => '2024-05-01'])
            ->assertUnprocessable();

        $this->putJson("/api/v1/units/{$id}", $this->payload([
            'fee_guarantor' => 'owner', 'house_number' => '12',
        ]))->assertOk();

        $this->postJson("/api/v1/units/{$id}/occupant", ['resident_id' => null, 'start_date' => '2024-05-01'])
            ->assertOk()->assertJsonPath('data.occupant_id', null);
    }

    public function test_resident_profile_status_is_computed(): void
    {
        $this->actingAsRole('admin');
        $resident = Resident::factory()->create();

        $this->getJson("/api/v1/residents/{$resident->id}")->assertJsonPath('data.profile_status', null);

        $unit = Unit::factory()->create(['owner_id' => $resident->id]);
        $this->getJson("/api/v1/residents/{$resident->id}")->assertJsonPath('data.profile_status', 'owner');

        $unit->update(['occupant_id' => $resident->id]);
        $this->getJson("/api/v1/residents/{$resident->id}")->assertJsonPath('data.profile_status', 'both');
        $this->getJson('/api/v1/residents?profile_status=both')->assertJsonCount(1, 'data');
    }

    public function test_vehicle_plate_is_normalized_and_unique(): void
    {
        $this->actingAsRole('admin');
        $unit = Unit::factory()->create();
        $body = ['vehicle_type' => 'car', 'plate_number' => 'd 1234 abc', 'brand' => 'Toyota', 'model' => 'Avanza', 'color' => 'Hitam'];

        $this->postJson("/api/v1/units/{$unit->id}/vehicles", $body)
            ->assertCreated()->assertJsonPath('data.plate_number', 'D 1234 ABC');

        $this->postJson("/api/v1/units/{$unit->id}/vehicles", [...$body, 'plate_number' => 'D1234ABC'])
            ->assertUnprocessable()->assertJsonValidationErrors('plate_number');
    }

    public function test_water_meter_install_and_replace(): void
    {
        $this->actingAsRole('admin');
        $unit = Unit::factory()->create();

        $this->postJson("/api/v1/units/{$unit->id}/water-meters", [
            'meter_number' => 'MTR-001', 'initial_value' => 0, 'installed_at' => '2024-01-01',
        ])->assertCreated()->assertJsonPath('data.is_active', true);

        $this->postJson("/api/v1/units/{$unit->id}/water-meters", [
            'meter_number' => 'MTR-002', 'initial_value' => 0, 'installed_at' => '2024-01-01',
        ])->assertUnprocessable();

        $this->postJson("/api/v1/units/{$unit->id}/water-meters/replace", [
            'old_final_value' => 100, 'meter_number' => 'MTR-002', 'initial_value' => 0,
            'replaced_at' => '2026-01-01', 'replace_reason' => 'broken',
        ])->assertCreated()->assertJsonPath('data.meter_number', 'MTR-002');

        $this->assertDatabaseHas('water_meters', ['meter_number' => 'MTR-001', 'is_active' => false, 'final_value' => 100]);
        $this->getJson("/api/v1/units/{$unit->id}/water-meters")->assertJsonCount(2, 'data');
    }

    public function test_vacant_land_cannot_have_meter(): void
    {
        $this->actingAsRole('admin');
        $unit = Unit::factory()->create(['is_vacant_land' => true, 'building_area' => null, 'building_status' => null]);

        $this->postJson("/api/v1/units/{$unit->id}/water-meters", [
            'meter_number' => 'MTR-009', 'initial_value' => 0, 'installed_at' => '2024-01-01',
        ])->assertUnprocessable();
    }

    public function test_permissions(): void
    {
        $this->actingAsRole('meter_reader');
        $this->getJson('/api/v1/units')->assertOk();
        $this->postJson('/api/v1/units', $this->payload())->assertForbidden();

        $unit = Unit::factory()->create();
        $this->postJson("/api/v1/units/{$unit->id}/water-meters", [
            'meter_number' => 'MTR-777', 'initial_value' => 0, 'installed_at' => '2024-01-01',
        ])->assertCreated();   // petugas meter boleh memasang meteran

        $this->actingAsRole('penjaga_pos');
        $this->getJson('/api/v1/units')->assertForbidden();
    }
}