<?php

namespace App\Modules\DoctorEngagement\Requests;

use App\Core\Enums\ReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'       => ['required', 'string', Rule::in([
                ReportStatus::ActionTaken->value,
                ReportStatus::Resolved->value,
                ReportStatus::Dismissed->value,
            ])],
            'admin_action' => ['nullable', 'string', 'max:1000'],
            'admin_notes'  => ['nullable', 'string', 'max:2000'],
        ];
    }
}
