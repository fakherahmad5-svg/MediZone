<?php

namespace App\Modules\Auth\Requests;

use App\Core\Enums\Gender;
use App\Core\Enums\UserRole;
use App\Models\Clinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            'ID_card_number' => [
                'required',
                'string',
                'regex:/^[0-9]+$/',
                'max:50',
                Rule::unique('users', 'ID_card_number'),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string',Password::min(8)->mixedCase()->numbers(), 'confirmed'],
            'clinic_code' => [
                'bail','required_if:role,receptionist', 'string', 'size:10',
                function ($attribute, $value, $fail) {
                    if (!Clinic::isValidCodeFormat($value)) {
                        $fail('Invalid clinic code format.');
                    }
                },
                'exists:clinics,code',
            ],
            ];


    }

    public function messages(): array
    {
        return [
            'email.unique'           => 'An account with this email already exists.',
            'phone.unique'           => 'An account with this phone number already exists.',
            'license_number.unique'  => 'This license number is already registered.',
            'role.in'                => 'Role must be one of: patient, doctor, or receptionist.',
            'password.confirmed'     => 'Password confirmation does not match.',
            'clinic_id.exists'       => 'Selected clinic does not exist.',
            'department_id.exists'   => 'Selected department does not exist.',
            'dob.before'             => 'Date of birth must be a past date.',
        ];
    }
}
