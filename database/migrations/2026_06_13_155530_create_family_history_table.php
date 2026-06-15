<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_history_id')->constrained('medical_history')->cascadeOnDelete();
            $table->string('condition');
            $table->string('relation');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_history');
    }
};