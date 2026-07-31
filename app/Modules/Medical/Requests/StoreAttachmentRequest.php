<?php

namespace App\Modules\Medical\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreAttachmentRequest
 *
 * max:10240 = 10MB. أنواع مسموحة تغطي الحالات الشائعة لمرفقات طبية
 * (نتائج تحاليل، صور أشعة، تقارير PDF ممسوحة ضوئياً).
 */
class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,doc,docx',
            ],
            'type' => [
                'sometimes',
                'nullable',
                'string',

            ],
        ];
    }
}
