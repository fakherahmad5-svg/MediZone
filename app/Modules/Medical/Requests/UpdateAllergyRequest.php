<?php

namespace App\Modules\Medical\Requests;

use App\Core\Enums\AllergyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAllergyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allergen' => ['sometimes', 'string', 'max:150'],
            'reaction' => ['sometimes', 'nullable', 'string', 'max:255'],
            'severity' => ['sometimes', 'nullable', 'string', Rule::in(AllergyType::values())],
        ];
    }
}
