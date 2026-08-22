<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            // NULL user_id = محفظة المنصة (is_platform = true) - صف واحد وحيد،
            // بينشئه WalletService::platformWallet() عبر firstOrCreate.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('is_platform')->default(false);
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            $table->index('is_platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
