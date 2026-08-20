<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * add_unique_to_doctor_reviews
 *
 * الحالة: [NEW - Phase 10]
 *
 * قرار مُتَّفق عليه: مراجعة واحدة لكل (patient_id, doctor_id) — تعديل
 * لاحق يُحدِّث نفس الصف (updateOrCreate)، وليس مراجعة جديدة لكل زيارة.
 * لم تصلني migration doctor_reviews الأصلي فلا أعرف إن كان القيد
 * موجوداً فعلاً — يُضاف هنا صراحة بدل افتراض وجوده.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_reviews', function (Blueprint $table) {
            $table->unique(['patient_id', 'doctor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('doctor_reviews', function (Blueprint $table) {
            $table->dropUnique(['patient_id', 'doctor_id']);
        });
    }
};
