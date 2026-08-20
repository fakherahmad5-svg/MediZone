<?php

use App\Core\Enums\CashDepositType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('doctor_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('doctor_id')->unique()->constrained('doctors')->cascadeOnDelete();
            $table->enum('cash_deposit_type', CashDepositType::values())->default(CashDepositType::Percentage->value); 
            $table->decimal('cash_deposit_value', 10, 2)->default(20.00);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_settings');
    }
};
