<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->cascadeOnDelete()->nullOnDelete();
            $table->foreignId('patient_record_id')->constrained('patient_records')->cascadeOnDelete();
            $table->string('visit_type')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['patient_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
