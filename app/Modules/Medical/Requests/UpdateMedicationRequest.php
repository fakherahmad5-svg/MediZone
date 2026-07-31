<?php

namespace App\Modules\Medical\Requests;

use App\Core\Enums\MedicationRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dosage'    => ['sometimes', 'string', 'max:100'],
            'frequency' => ['sometimes', 'string', 'max:100'],
            'route'      => ['nullable', 'string', Rule::in(MedicationRoute::values())],
            'notes'     => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
