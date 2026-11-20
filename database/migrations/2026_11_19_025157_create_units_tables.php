<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable();
            $table->foreignId('street_id')->constrained('streets');
            $table->foreignId('block_id')->constrained('blocks');
            $table->string('house_number', 20);
            $table->foreignId('zone_id')->constrained('zones');
            $table->foreignId('village_id')->constrained('villages');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Pemilik dan penghuni SAAT INI. Riwayat ada di tabel terpisah
            $table->foreignId('owner_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->foreignId('occupant_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->enum('fee_guarantor', ['owner', 'occupant'])->default('owner');
            $table->enum('water_tariff_category', ['general', 'commercial'])->default('general');

            $table->decimal('land_area', 15, 2);
            $table->boolean('is_vacant_land')->default(false);
            $table->decimal('building_area', 15, 2)->nullable();
            $table->enum('building_status', ['occupied', 'rented', 'vacation_home', 'empty'])->nullable();
            $table->enum('rental_type', ['daily', 'monthly', 'yearly'])->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['street_id', 'block_id', 'house_number']);
        });

        foreach (['unit_ownerships', 'unit_occupancies'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->foreignId('unit_id')->constrained('units');
                $table->foreignId('resident_id')->constrained('residents');
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['unit_id', 'end_date']);
            });
        }

        Schema::create('water_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units');
            $table->string('meter_number', 50)->unique();
            $table->decimal('initial_value', 12, 2);
            $table->decimal('final_value', 12, 2)->nullable();
            $table->date('installed_at');
            $table->date('replaced_at')->nullable();
            $table->string('replace_reason', 30)->nullable();
            $table->boolean('is_active')->default(true);
            // Terisi unit_id hanya jika aktif. Unik = maksimal satu meteran aktif per unit
            $table->unsignedBigInteger('active_unit_id')->nullable()
                ->storedAs('IF(is_active = 1, unit_id, NULL)');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('active_unit_id');
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units');
            $table->foreignId('resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->enum('vehicle_type', ['motorcycle', 'car']);
            $table->string('plate_number', 15);
            $table->string('plate_key', 15)->unique();   // tanpa spasi, untuk cek duplikat
            $table->string('brand', 50);
            $table->string('model', 50);
            $table->string('color', 30);
            $table->string('sticker_code', 30)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('water_meters');
        Schema::dropIfExists('unit_occupancies');
        Schema::dropIfExists('unit_ownerships');
        Schema::dropIfExists('units');
    }
};