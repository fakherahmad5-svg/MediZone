<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
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
            ])->unique();
            $table->string('title_en');
            $table->string('title_ar');
            $table->text('body_en');
            $table->text('body_ar');
            $table->json('channels');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};