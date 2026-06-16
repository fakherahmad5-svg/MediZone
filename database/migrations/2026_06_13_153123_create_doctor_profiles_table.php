<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->text('biography')->nullable();
            $table->json('qualifications')->nullable();
            $table->decimal('consultation_fee', 10, 2)->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->json('languages')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_profiles');
    }
};
