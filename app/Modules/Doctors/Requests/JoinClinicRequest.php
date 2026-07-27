<?php

namespace App\Modules\Doctors\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JoinClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ownedDepartmentIds = $this->user()->doctor
            ?->departments()
            ->pluck('departments.id')
            ->unique()
            ->values()
            ->all() ?? [];

        return [
            'clinic_id'        => ['required', 'integer', 'exists:clinics,id'],
            'department_ids'   => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', 'distinct', Rule::in($ownedDepartmentIds)],
        ];
    }

    public function messages(): array
    {
        return [
            'department_ids.*.in' => 'You can only select from your own registered specialties.',
        ];
    }
}
