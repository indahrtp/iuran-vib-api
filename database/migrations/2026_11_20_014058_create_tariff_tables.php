<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    private function audit(Blueprint $table): void
    {
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
    }

    public function up(): void
    {
        Schema::create('ipl_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('rate_per_m2', 15, 2);
            $table->string('coa_code', 30);
            $table->date('valid_from')->index();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $this->audit($table);
        });

        Schema::create('other_fees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('amount', 15, 2);
            $table->string('coa_code', 30);
            $table->date('valid_from')->index();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $this->audit($table);
        });

        Schema::create('penalty_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('due_day');          // tanggal jatuh tempo tiap bulan
            $table->unsignedTinyInteger('grace_days');       // masa tenggang
            $table->decimal('rate_percent', 5, 2);           // persen dari total tagihan
            $table->decimal('max_amount', 15, 2)->nullable(); // boleh kosong
            $table->string('coa_code', 30);
            $table->date('valid_from')->index();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $this->audit($table);
        });

        Schema::create('water_tariff_schedules', function (Blueprint $table) {
            $table->id();
            $table->decimal('base_fee', 15, 2)->default(0);   // biaya tetap/abonemen, menunggu B1
            $table->string('base_fee_coa_code', 30)->nullable();
            $table->date('valid_from')->index();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $this->audit($table);
        });

        Schema::create('water_tariff_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('water_tariff_schedules')->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order');
            $table->string('name', 100);
            $table->decimal('min_value', 10, 2);
            $table->decimal('max_value', 10, 2)->nullable();   // NULL = tak terbatas
            $table->enum('charge_type', ['flat', 'per_m3']);
            $table->decimal('general_rate', 15, 2);
            $table->decimal('commercial_rate', 15, 2);
            $table->string('coa_code', 30);
            $table->timestamps();

            $table->unique(['schedule_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('water_tariff_tiers');
        Schema::dropIfExists('water_tariff_schedules');
        Schema::dropIfExists('penalty_settings');
        Schema::dropIfExists('other_fees');
        Schema::dropIfExists('ipl_rates');
    }
};
