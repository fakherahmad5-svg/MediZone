<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_record_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_id')->constrained('patient_doctor_access')->cascadeOnDelete();
            $table->enum('action', [
                'view_record',
                'view_history',
                'view_medications',
                'view_attachments',
                'create_encounter',
                'create_note',
                'create_prescription',
                'export_record'
            ]);
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('accessed_at');
            $table->timestamps();

            $table->index(['access_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_access_logs');
    }
};