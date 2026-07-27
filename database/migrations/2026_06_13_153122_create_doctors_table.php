<?php

use App\Core\Enums\DoctorVerificationStatus;
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
            $table->timestamps();
            $table->softDeletes();
            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
