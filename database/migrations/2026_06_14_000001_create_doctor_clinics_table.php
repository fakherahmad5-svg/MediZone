<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_clinics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->decimal('consultation_fee', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['doctor_id', 'clinic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_clinics');
    }
};
