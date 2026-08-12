<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;


class SetWeeklyScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consultation_duration' => ['required', 'integer', 'min:5', 'max:240'],
            'break_duration'        => ['sometimes', 'integer', 'min:0', 'max:120'],
            'buffer_enabled'        => ['sometimes', 'boolean'],
            'max_patients'          => ['sometimes', 'nullable', 'integer', 'min:1'],

            'days'                    => ['required', 'array', 'min:1'],
            'days.*.day_of_week'      => ['required', 'integer', 'between:0,6'],
            'days.*.sessions'         => ['required', 'array', 'min:1'],
            'days.*.sessions.*.session_type' => ['required', 'string', 'max:20'],
            'days.*.sessions.*.start_time'   => ['required', 'date_format:H:i'],
            'days.*.sessions.*.end_time'     => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * فحوصات لا يمكن التعبير عنها بقواعد بسيطة:
     *   1. لا تكرار لنفس day_of_week ضمن نفس الطلب
     *   2. end_time بعد start_time لكل جلسة (وليس فقط تنسيق صحيح)
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $days = $this->input('days', []);

            $seenDays = [];
            foreach ($days as $i => $day) {
                $dow = $day['day_of_week'] ?? null;

                if ($dow !== null) {
                    if (in_array($dow, $seenDays, true)) {
                        $v->errors()->add("days.{$i}.day_of_week", 'Duplicate day_of_week in the same request.');
                    }
                    $seenDays[] = $dow;
                }

                foreach ($day['sessions'] ?? [] as $j => $session) {
                    $start = $session['start_time'] ?? null;
                    $end   = $session['end_time'] ?? null;

                    if ($start && $end && $start >= $end) {
                        $v->errors()->add(
                            "days.{$i}.sessions.{$j}.end_time",
                            'end_time must be after start_time.'
                        );
                    }
                }
            }
        });
    }
}
