<?php

use App\Core\Enums\MedicationRoute;
use App\Core\Enums\MedicationSource;
use App\Core\Enums\MedicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_record_id')->constrained('patient_records')->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained('drugs')->cascadeOnDelete();
            $table->foreignId('prescription_item_id')->nullable();
            $table->enum('source', MedicationSource::values());
            $table->enum('status', MedicationStatus::values())->default(MedicationStatus::Active->value);
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->enum('route',MedicationRoute::values())->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('stopped_at')->nullable();
            $table->text('stop_reason')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->index(['patient_record_id', 'status']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
