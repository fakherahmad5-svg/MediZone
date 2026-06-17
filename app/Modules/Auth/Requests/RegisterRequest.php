<?php

namespace App\Modules\Auth\Requests;

use App\Core\Enums\Gender;
use App\Core\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = $this->input('role');

        return [
            'role' => ['required', Rule::in(UserRole::selfRegisterable())],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'dob' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(Gender::values())],
            'blood_type' => ['nullable', 'string', 'max:5'],
            'clinic_id' => [
                Rule::requiredIf(in_array($role, [UserRole::Doctor->value, UserRole::Receptionist->value], true)),
                'nullable',
                'integer',
                'exists:clinics,id',
            ],
            'department_id' => [
                Rule::requiredIf($role === UserRole::Doctor->value),
                'nullable',
                'integer',
                'exists:departments,id',
            ],
            'license_number' => [
                Rule::requiredIf($role === UserRole::Doctor->value),
                'nullable',
                'string',
                'max:50',
                'unique:doctors,license_number',
            ],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
        ];
    }
}
