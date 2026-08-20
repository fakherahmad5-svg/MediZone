<?php

namespace App\Modules\Auth\Requests;

use App\Core\Enums\Gender;
use App\Core\Enums\UserRole;
use App\Models\Clinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
       $role = UserRole::Doctor->value;


        return [
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'dob' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(Gender::values())],
            'device_name'=> ['nullable', 'string', 'max:100'],
            'address'=> ['nullable', 'string', 'max:100'],
            'blood_type' => ['nullable', 'string', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],

            // Doctor
//            'license_number' => [
//                Rule::requiredIf($role === UserRole::Doctor->value),
//                'nullable',
//                'string',
//                'max:50',
//                'unique:doctors,license_number',
//            ],
//            'experience_years' => ['nullable', 'integer', 'min:0'],
            'practice_start_date' => [
                Rule::requiredIf($role === UserRole::Doctor->value),
                'nullable', 'date', 'before:today',
            ],
            'department_ids' => [
                Rule::requiredIf($role === UserRole::Doctor->value),
                'nullable', 'array', 'min:1',
            ],
            'department_ids.*' => ['integer', 'distinct', 'exists:departments,id'],

            'registration_mode' => [$role === 'doctor' ? 'required' : 'prohibited', 'in:join_clinic,create_clinic'],

            'clinic_code' => [
                'bail','required_if:registration_mode,join_clinic',
                'nullable', 'string', 'size:10',
                function ($attribute, $value, $fail) {
                    if ($value !== null && ! Clinic::isValidCodeFormat($value)) {
                        $fail('Invalid clinic code format.');
                    }
                },
                'exists:clinics,code',
            ],
            'clinic_name'    => ['required_if:registration_mode,create_clinic', 'nullable', 'string', 'max:150'],
            'clinic_address' => ['required_if:registration_mode,create_clinic', 'nullable', 'string', 'max:255'],
            'clinic_phone'   => ['nullable', 'string', 'max:20'],
            'latitude'       => [ 'nullable', 'numeric'],
            'longitude'      => [ 'nullable', 'numeric'],
            'consultation_fee' => [
                Rule::requiredIf($role === UserRole::Doctor->value),
                'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'id_card' => [
                Rule::requiredIf($role === UserRole::Doctor->value),
                'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120',
            ],
            'photo' => [
                Rule::requiredIf($role === UserRole::Doctor->value),
                'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120',
            ],

            'license_file' => [
                Rule::requiredIf($role === UserRole::Doctor->value),
                'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120',
            ],
            'certificates'   => [Rule::requiredIf($role === UserRole::Doctor->value), 'array', 'min:1'],
            'certificates.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],

            'clinic_license_file' => [
                'required_if:registration_mode,create_clinic',
                'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120',
            ],

        ];
    }
}
