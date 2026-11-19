<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->text('nik');                          // terenkripsi
            $table->char('nik_hash', 64)->unique();       // HMAC untuk unik dan pencarian
            $table->string('name', 150)->index();
            $table->enum('gender', ['male', 'female']);
            $table->date('birth_date');

            $table->string('street_name', 150);
            $table->string('house_number', 20);
            $table->string('rt', 3);
            $table->string('rw', 3);
            $table->string('postal_code', 5);
            $table->string('province_code', 2);
            $table->string('city_code', 4);
            $table->string('district_code', 6);
            $table->string('village_code', 10)->index();

            $table->string('phone', 20)->index();
            $table->string('phone_2', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Melengkapi kolom users.resident_id dari Tahap 1
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('resident_id')->references('id')->on('residents')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['resident_id']);
        });
        Schema::dropIfExists('residents');
    }
};
