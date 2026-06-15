<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained('drugs')->cascadeOnDelete();
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->string('duration')->nullable();
            $table->string('route')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
         Schema::table('medications', function (Blueprint $table) {
        $table->foreign('prescription_item_id')
            ->references('id')->on('prescription_items')
            ->nullOnDelete();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};