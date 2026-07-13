<?php

use App\Core\Enums\ReceptionistsStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receptionists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ReceptionistsStatus::values())->default(ReceptionistsStatus::Pending->value);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receptionists');
    }
};
