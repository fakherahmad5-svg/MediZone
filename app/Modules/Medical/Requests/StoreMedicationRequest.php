<?php

namespace App\Modules\Medical\Requests;

use App\Core\Enums\MedicationRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class StoreMedicationRequest extends FormRequest
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
            'strength'   => ['sometimes', 'nullable', 'string', 'max:50'],
            'dosage'     => ['required', 'string', 'max:100'],
            'frequency'  => ['required', 'string', 'max:100'],
            'route'      => ['nullable', 'string', Rule::in(MedicationRoute::values())],
            'start_date' => ['nullable', 'date'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ];
    }
}
