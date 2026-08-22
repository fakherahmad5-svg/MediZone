<?php

use App\Core\Enums\AppointmentPaymentMethod;
use App\Core\Enums\AppointmentPaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained('appointments')->cascadeOnDelete();
            $table->enum('method', AppointmentPaymentMethod::values());
            $table->enum('status', AppointmentPaymentStatus::values())->default(AppointmentPaymentStatus::Pending->value);
            $table->decimal('total_amount', 12, 2);
            // ثابتة ٥٪ من total_amount وقت الدفع - عمولة المنصة، لا تُرد أبداً.
            $table->decimal('platform_fee_amount', 12, 2);
            // نصيب الطبيب الكامل (total_amount - platform_fee_amount) - المرجعي،
            // مو بالضرورة المبلغ الفعلي المحوّل (لو عربون بس، بينحول أقل منه).
            $table->decimal('doctor_amount', 12, 2);
            $table->unsignedTinyInteger('deposit_percent')->nullable();
            $table->decimal('deposit_amount', 12, 2)->nullable();
            // مجموع الفعلي المسحوب من محفظة المريض لهالموعد (عربون أو كامل).
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_payments');
    }
};
