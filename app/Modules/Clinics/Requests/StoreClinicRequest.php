<?php

namespace App\Modules\Clinics\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreClinicRequest — إنشاء عيادة مباشرة من الأدمن (تصبح active فوراً)
 */
class StoreClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:150'],
            'phone'     => ['nullable', 'string', 'max:20'],
            //'email'     => ['nullable', 'email', 'max:255'],
            'address'   => ['nullable', 'string', 'max:255'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
