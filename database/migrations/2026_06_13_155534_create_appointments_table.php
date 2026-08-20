<?php

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\ConsultationType;
use App\Core\Enums\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained('doctor_time_slots')->cascadeOnDelete();
            $table->enum('status', AppointmentStatus::values())->default(AppointmentStatus::Scheduled->value);
            $table->enum('encounter_type', ConsultationType::values());
            $table->enum('payment_method', PaymentMethod::values());
            $table->string('deposit_type')->nullable();
            $table->decimal('deposit_percentage', 5, 2)->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('commission_percentage', 5, 2);
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->decimal('remaining_cash_amount', 10, 2)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['doctor_id', 'status']);
            $table->index(['patient_id', 'status']);
            $table->index(['clinic_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
