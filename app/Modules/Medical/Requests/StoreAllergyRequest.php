<?php

namespace App\Modules\Medical\Requests;

use App\Core\Enums\AllergySeverity;
use App\Core\Enums\AllergyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAllergyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allergen' => ['required', 'string', 'max:150'],
            'allergen_type' => ['required', Rule::in(AllergyType::values())],
            'reaction' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', 'string', Rule::in(AllergySeverity::values())],
        ];
    }
}
