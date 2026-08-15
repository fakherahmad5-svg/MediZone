<?php

namespace App\Modules\Doctors\Requests;

use Illuminate\Foundation\Http\FormRequest;


class UpdateDoctorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'first_name'           => ['sometimes', 'string', 'max:100'],
            'last_name'            => ['sometimes', 'string', 'max:100'],

            'phone'                => ['sometimes', 'string', 'max:20', 'unique:users,phone,' . $this->user()->id],
            'address'              => ['sometimes', 'string', 'max:255'],


            'biography'            => ['sometimes', 'string', 'max:2000'],
            'qualifications'       => ['sometimes', 'array'],
            'qualifications.*.degree'      => ['required_with:qualifications', 'string', 'max:150'],
            'qualifications.*.institution' => ['required_with:qualifications', 'string', 'max:150'],
            'qualifications.*.year'        => ['required_with:qualifications', 'integer', 'min:1950'],
            'consultation_fee'     => ['sometimes', 'numeric', 'min:0'],
            'languages'            => ['sometimes', 'array'],
            'languages.*'          => ['string', 'max:50'],
        ];
    }
}
