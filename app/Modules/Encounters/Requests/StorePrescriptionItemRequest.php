<?php

namespace App\Modules\Encounters\Requests;

use App\Core\Enums\MedicationRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * الحالة: REPLACE — [تصحيح] route يُفرَض عليه نفس قائمة قيم
 * MedicationRoute (enum حقيقي على مستوى القاعدة في medications)
 * رغم أن عمود prescription_items.route نص حر بلا قيد DB — التحقق هنا
 * يضمن أن نسخ القيمة لاحقاً إلى Medication.route
 * (EncounterService::addPrescriptionItem) لن يفشل بخطأ SQL أبداً.
 */
class StorePrescriptionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'drug_name'  => ['required', 'string', 'max:150'],
            'form'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'dosage'    => ['nullable', 'string', 'max:255'],
            'frequency' => ['nullable', 'string', 'max:255'],
            'duration'  => ['nullable', 'string', 'max:255'],
            'route'     => ['nullable', 'string', Rule::in(MedicationRoute::values())],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ];
    }
}
