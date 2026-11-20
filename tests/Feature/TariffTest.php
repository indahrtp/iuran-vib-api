<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\IplRate;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TariffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        ChartOfAccount::create(['code' => '4-1001', 'name' => 'Pendapatan IPL']);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    private function tiers(): array
    {
        return [
            ['name' => 'Tier 1', 'min_value' => 0, 'max_value' => 10, 'charge_type' => 'flat',
                'general_rate' => 20000, 'commercial_rate' => 30000, 'coa_code' => '4-1001'],
            ['name' => 'Tier 2', 'min_value' => 10, 'max_value' => 20, 'charge_type' => 'per_m3',
                'general_rate' => 3000, 'commercial_rate' => 4000, 'coa_code' => '4-1001'],
            ['name' => 'Tier 3', 'min_value' => 20, 'max_value' => null, 'charge_type' => 'per_m3',
                'general_rate' => 5000, 'commercial_rate' => 6000, 'coa_code' => '4-1001'],
        ];
    }

    public function test_ipl_versions_chain_and_current_rate(): void
    {
        $this->actingAsRole('admin');
        $past = now()->subYear()->toDateString();
        $future = now()->addMonth()->toDateString();

        $first = $this->postJson('/api/v1/ipl-rates', ['rate_per_m2' => 2000, 'coa_code' => '4-1001', 'valid_from' => $past])
            ->assertCreated()->json('data.id');

        // Tanggal mundur ditolak
        $this->postJson('/api/v1/ipl-rates', ['rate_per_m2' => 1500, 'coa_code' => '4-1001', 'valid_from' => now()->subYears(2)->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('valid_from');

        $second = $this->postJson('/api/v1/ipl-rates', ['rate_per_m2' => 2500, 'coa_code' => '4-1001', 'valid_from' => $future])
            ->assertCreated()->json('data.id');

        // Versi lama ditutup sehari sebelum versi baru
        $this->assertDatabaseHas('ipl_rates', [
            'id' => $first, 'valid_until' => Carbon::parse($future)->subDay()->toDateString(),
        ]);

        $current = $this->getJson('/api/v1/ipl-rates/current')->assertOk();
        $this->assertEquals(2000, $current->json('data.rate_per_m2'));
        $this->assertSame('scheduled', $this->getJson("/api/v1/ipl-rates/{$second}")->json('data.period_status'));

        // Yang sudah berlaku terkunci, yang belum berlaku boleh diubah dan dihapus
        $this->putJson("/api/v1/ipl-rates/{$first}", ['rate_per_m2' => 1, 'coa_code' => '4-1001'])->assertUnprocessable();
        $this->deleteJson("/api/v1/ipl-rates/{$first}")->assertStatus(409);

        $this->putJson("/api/v1/ipl-rates/{$second}", ['rate_per_m2' => 2600, 'coa_code' => '4-1001'])->assertOk();

        $this->deleteJson("/api/v1/ipl-rates/{$second}")->assertNoContent();
        $this->assertDatabaseHas('ipl_rates', ['id' => $first, 'valid_until' => null]);
    }

    public function test_coa_must_exist_and_rate_must_be_positive(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/v1/ipl-rates', ['rate_per_m2' => 0, 'coa_code' => 'XXX', 'valid_from' => now()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors(['rate_per_m2', 'coa_code']);
    }

    public function test_ipl_simulation_uses_land_area(): void
    {
        $this->actingAsRole('admin');
        IplRate::create(['rate_per_m2' => 2000, 'coa_code' => '4-1001', 'valid_from' => now()->subMonth()->toDateString()]);
        $unit = Unit::factory()->create(['land_area' => 150]);

        $this->assertEquals(240000, $this->getJson('/api/v1/ipl-rates/simulate?land_area=120')->json('data.amount'));
        $this->assertEquals(300000, $this->getJson("/api/v1/ipl-rates/simulate?unit_id={$unit->id}")->json('data.amount'));
    }

    public function test_simulation_without_rate_returns_422(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/v1/ipl-rates/simulate?land_area=100')->assertUnprocessable();
    }

    public function test_penalty_setting_validation_and_switch(): void
    {
        $this->actingAsRole('admin');
        $body = ['due_day' => 20, 'grace_days' => 5, 'rate_percent' => 2, 'coa_code' => '4-1001', 'valid_from' => now()->subMonth()->toDateString()];

        $this->postJson('/api/v1/penalty-settings', [...$body, 'due_day' => 31])
            ->assertUnprocessable()->assertJsonValidationErrors('due_day');

        $id = $this->postJson('/api/v1/penalty-settings', $body)->assertCreated()
            ->assertJsonPath('data.max_amount', null)->json('data.id');

        $this->assertNotNull($this->getJson('/api/v1/penalty-settings/current')->json('data'));

        $this->patchJson("/api/v1/penalty-settings/{$id}/active", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertNull($this->getJson('/api/v1/penalty-settings/current')->json('data'));
    }

    public function test_other_fees_lifecycle(): void
    {
        $this->actingAsRole('admin');
        $body = ['name' => 'Iuran Keamanan', 'amount' => 50000, 'coa_code' => '4-1001', 'valid_from' => now()->subMonth()->toDateString()];

        $id = $this->postJson('/api/v1/other-fees', $body)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/other-fees', [...$body, 'name' => 'Iuran Sampah', 'amount' => 20000])->assertCreated();

        $this->getJson('/api/v1/other-fees?effective_on='.now()->toDateString())->assertJsonCount(2, 'data');

        // Nominal terkunci setelah berlaku, nama dan status masih boleh diubah
        $this->putJson("/api/v1/other-fees/{$id}", [...$body, 'amount' => 99999])->assertUnprocessable();
        $this->putJson("/api/v1/other-fees/{$id}", [...$body, 'name' => 'Keamanan 24 Jam', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->getJson('/api/v1/other-fees?effective_on='.now()->toDateString())->assertJsonCount(1, 'data');
        $this->deleteJson("/api/v1/other-fees/{$id}")->assertStatus(409);
    }

    public function test_water_tiers_must_be_continuous(): void
    {
        $this->actingAsRole('admin');
        $from = now()->subMonth()->toDateString();

        $gap = $this->tiers();
        $gap[1]['min_value'] = 12;
        $this->postJson('/api/v1/water-tariffs', ['valid_from' => $from, 'tiers' => $gap])
            ->assertUnprocessable()->assertJsonValidationErrors('tiers');

        $bounded = $this->tiers();
        $bounded[2]['max_value'] = 50;
        $this->postJson('/api/v1/water-tariffs', ['valid_from' => $from, 'tiers' => $bounded])
            ->assertUnprocessable()->assertJsonValidationErrors('tiers');

        $this->postJson('/api/v1/water-tariffs', ['valid_from' => $from, 'tiers' => $this->tiers()])
            ->assertCreated()->assertJsonCount(3, 'data.tiers');
    }

    public function test_water_bill_simulation(): void
    {
        $this->actingAsRole('admin');
        $this->postJson('/api/v1/water-tariffs', ['valid_from' => now()->subMonth()->toDateString(), 'tiers' => $this->tiers()])
            ->assertCreated();

        // 25 m3, umum: 20.000 (flat) + 10 x 3.000 + 5 x 5.000 = 75.000
        $res = $this->postJson('/api/v1/water-tariffs/simulate', ['usage' => 25, 'category' => 'general'])->assertOk();
        $this->assertEquals(75000, $res->json('data.total'));
        $this->assertCount(3, $res->json('data.items'));

        // 5 m3: hanya tier flat
        $this->assertEquals(20000, $this->postJson('/api/v1/water-tariffs/simulate', ['usage' => 5, 'category' => 'general'])->json('data.total'));

        // 0 m3 tetap kena tarif minimum tier 1
        $this->assertEquals(20000, $this->postJson('/api/v1/water-tariffs/simulate', ['usage' => 0, 'category' => 'general'])->json('data.total'));

        // Komersial: 30.000 + 10 x 4.000 + 5 x 6.000 = 100.000
        $this->assertEquals(100000, $this->postJson('/api/v1/water-tariffs/simulate', ['usage' => 25, 'category' => 'commercial'])->json('data.total'));
    }

    public function test_permissions(): void
    {
        $this->actingAsRole('pengurus');
        $this->getJson('/api/v1/ipl-rates')->assertOk();
        $this->postJson('/api/v1/ipl-rates', ['rate_per_m2' => 1, 'coa_code' => '4-1001', 'valid_from' => now()->toDateString()])->assertForbidden();

        $this->actingAsRole('bendahara');
        $this->postJson('/api/v1/ipl-rates', ['rate_per_m2' => 1000, 'coa_code' => '4-1001', 'valid_from' => now()->toDateString()])->assertCreated();

        $this->actingAsRole('meter_reader');
        $this->getJson('/api/v1/ipl-rates')->assertForbidden();
    }
}