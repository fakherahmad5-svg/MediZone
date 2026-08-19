<?php

namespace App\Modules\Encounters\Requests;

use App\Core\Enums\MedicationRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * SubmitEncounterRequest
 *
 * الحالة: [NEW - Phase 8 Addendum] بديل اختياري لاستدعاء notes/
 * diagnoses/prescription-items كـ 3 طلبات منفصلة — يقبلهم معاً في
 * طلب واحد. كل قسم اختياري بمفرده، لكن يجب أن يصل قسم واحد على
 * الأقل (يتحقق منه withValidator() أدناه).
 *
 * الحقول الفرعية مطابقة حرفياً لـ StoreClinicalNoteRequest/
 * StoreDiagnosisRequest/StorePrescriptionItemRequest — لا قواعد
 * تحقق جديدة اخترعتُها، فقط أُعيد استخدام نفس القواعد داخل مصفوفات.
 */
class SubmitEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes'                     => ['sometimes', 'array'],
            'notes.*.content'           => ['required', 'string', 'max:5000'],

            'diagnoses'                 => ['sometimes', 'array'],
            'diagnoses.*.label'         => ['required', 'string', 'max:255'],
            'diagnoses.*.description'   => ['nullable', 'string', 'max:2000'],

            'prescription_items'            => ['sometimes', 'array'],
            'prescription_items.*.drug_id'   => ['required', 'integer', 'exists:drugs,id'],
            'prescription_items.*.dosage'    => ['nullable', 'string', 'max:255'],
            'prescription_items.*.frequency' => ['nullable', 'string', 'max:255'],
            'prescription_items.*.duration'  => ['nullable', 'string', 'max:255'],
            'prescription_items.*.route'     => ['nullable', 'string', Rule::in(MedicationRoute::values())],
            'prescription_items.*.notes'     => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $notes = $this->input('notes', []);
            $diagnoses = $this->input('diagnoses', []);
            $items = $this->input('prescription_items', []);

            if (empty($notes) && empty($diagnoses) && empty($items)) {
                $validator->errors()->add(
                    'notes',
                    'Provide at least one note, diagnosis, or prescription item.'
                );
            }
        });
    }
}
