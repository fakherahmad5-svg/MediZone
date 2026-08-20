<?php

use App\Core\Enums\RefundStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();

            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('original_amount', 10, 2); 
            $table->decimal('admin_fee_percentage', 5, 2);
            $table->decimal('admin_fee_amount', 10, 2);
            $table->decimal('refund_amount', 10, 2);

            $table->enum('status', RefundStatus::values())->default(RefundStatus::Pending->value);

            $table->string('stripe_refund_id')->nullable()->unique();
            $table->text('failure_reason')->nullable();

            $table->text('cancellation_reason')->nullable();

           
            $table->timestamp('cancelled_at');
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index(['payment_id', 'status']);
            $table->index(['appointment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};