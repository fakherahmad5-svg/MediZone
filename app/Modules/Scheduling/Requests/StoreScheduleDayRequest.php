<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleDayRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'days'                           => ['required', 'array', 'min:1'],
            'days.*.day_of_week'             => ['required', 'integer', 'min:0', 'max:6'],
            'days.*.sessions'                => ['required', 'array', 'min:1'],
            'days.*.sessions.*.session_type' => ['required', 'string', 'in:morning,evening,night'],
            'days.*.sessions.*.start_time'   => ['required', 'date_format:H:i'],
            'days.*.sessions.*.end_time'     => ['required', 'date_format:H:i'],
        ];
    }
}
