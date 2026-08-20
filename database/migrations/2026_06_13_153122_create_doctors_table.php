<?php

use App\Core\Enums\DoctorVerificationStatus;
use App\Core\Enums\StripeAccountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            
        
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('practice_start_date')->nullable();
            $table->enum('verification_status', DoctorVerificationStatus::values())->default(DoctorVerificationStatus::Pending->value);
            $table->string('stripe_connect_id')->nullable();
            $table->boolean('stripe_active')->default(false);
            $table->decimal('examination_fee', 10, 2)->nullable();
            $table->decimal('commission_percentage', 5, 2)->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('verification_status');
            $table->index('stripe_connect_id');
            $table->enum('stripe_account_type', StripeAccountType::values())->nullable();
            $table->index('stripe_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};