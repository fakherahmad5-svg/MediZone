<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;


class StoreBlockedTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'block_date'  => ['required_without:day_of_week', 'nullable', 'date', 'after_or_equal:today'],
            'day_of_week' => ['required_without:block_date', 'nullable', 'integer', 'between:0,6'],
            'start_time'  => ['required', 'date_format:H:i'],
            'end_time'    => ['required', 'date_format:H:i', 'after:start_time'],
            'reason'      => ['nullable', 'string', 'max:255'],
        ];
    }
}
