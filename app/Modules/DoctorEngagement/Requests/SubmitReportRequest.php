<?php

namespace App\Modules\DoctorEngagement\Requests;

use App\Core\Enums\ReportCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category'     => ['required', 'string', Rule::in(ReportCategory::values())],
            'description'  => ['required', 'string', 'max:2000'],
            'encounter_id' => ['nullable', 'integer', 'exists:encounters,id'],
        ];
    }
}
