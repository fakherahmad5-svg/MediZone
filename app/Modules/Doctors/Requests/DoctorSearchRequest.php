<?php

namespace App\Modules\Doctors\Requests;

use Illuminate\Foundation\Http\FormRequest;


class DoctorSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Basic search
            'department_id'   => ['sometimes', 'integer', 'exists:departments,id'],
            'clinic_id'       => ['sometimes', 'integer', 'exists:clinics,id'],
            'name'            => ['sometimes', 'string', 'max:100'],

            // Location - GPS only for now (City/Area deferred)
            'latitude'        => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude'       => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'radius_km'       => ['sometimes', 'numeric', 'min:1', 'max:100'],

            // Experience (years), derived from doctors.practice_start_date
            'experience_min'  => ['sometimes', 'integer', 'min:0', 'max:60'],
            'experience_max'  => ['sometimes', 'integer', 'min:0', 'max:60', 'gte:experience_min'],

            // Consultation price - assumes doctor_clinics.consultation_fee
            'price_min'       => ['sometimes', 'numeric', 'min:0'],
            'price_max'       => ['sometimes', 'numeric', 'min:0', 'gte:price_min'],

            // Doctor gender - matches users.gender exactly (Gender enum)
            'gender'          => ['sometimes', 'string', 'in:male,female'],

            // Availability on App
            'availability'    => ['sometimes', 'string', 'in:today,tomorrow,this_week,custom'],
            'custom_date'     => ['required_if:availability,custom', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],

            // Time Slot (multi-select) - matches schedule_sessions.session_type
            'time_slot'       => ['sometimes', 'array'],
            'time_slot.*'     => ['string', 'in:morning,afternoon,evening'],

            // Sorting - reviews intentionally excluded (no reviews table yet)
            'sort'            => ['sometimes', 'string', 'in:best_match,fee_asc,fee_desc,experience'],

            'per_page'        => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required_with'  => 'يجب إرسال latitude عند إرسال longitude.',
            'longitude.required_with' => 'يجب إرسال longitude عند إرسال latitude.',
            'custom_date.required_if' => 'يجب إرسال custom_date عندما تكون availability = custom.',
            'experience_max.gte'      => 'يجب أن يكون experience_max أكبر من أو يساوي experience_min.',
            'price_max.gte'           => 'يجب أن يكون price_max أكبر من أو يساوي price_min.',
        ];
    }
}
