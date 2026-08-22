<?php

use App\Core\Enums\WalletTransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->enum('type', WalletTransactionType::values());
            // amount موقّع: موجب = إيداع، سالب = سحب. balance_after = رصيد
            // المحفظة فور تنفيذ هالحركة (سجل تاريخي ثابت، ما بيتغير لاحقاً).
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            // مرجع اختياري (Appointment / TopUpRequest / WithdrawalRequest) -
            // شو سبب هالحركة بالتحديد.
            $table->nullableMorphs('related');
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['wallet_id', 'type']);
            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
