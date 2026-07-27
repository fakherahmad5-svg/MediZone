<?php

namespace App\Modules\Receptionists\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectReceptionistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
