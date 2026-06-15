<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_doctor_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->enum('access_type', ['read_only', 'full'])->default('read_only');
            $table->enum('status', ['active', 'revoked', 'expired'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revoke_reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'doctor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_doctor_access');
    }
};