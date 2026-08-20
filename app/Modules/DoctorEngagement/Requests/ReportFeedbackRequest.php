<?php

namespace App\Modules\DoctorEngagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'feedback' => ['required', 'string', 'max:1000'],
        ];
    }
}
