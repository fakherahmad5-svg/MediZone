<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', [
                'appointment_confirmed',
                'appointment_cancelled',
                'appointment_reminder',
                'appointment_rescheduled',
                'appointment_completed',
                'consultation_started',
                'consultation_message_received',
                'consultation_ended',
                'payment_confirmed',
                'payment_failed',
                'invoice_issued',
                'access_granted',
                'access_revoked',
                'doctor_verified',
                'doctor_rejected',
                'report_received',
                'system_alert'
            ]);
            $table->enum('status', ['pending', 'sent', 'failed', 'read'])->default('pending');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->json('channels_sent')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};