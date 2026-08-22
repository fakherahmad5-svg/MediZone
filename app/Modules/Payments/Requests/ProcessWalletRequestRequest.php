<?php

namespace App\Modules\Payments\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * جسم طلب رفض (reject) أي من TopUpRequest/WithdrawalRequest من الأدمن -
 * admin_note اختياري (سبب الرفض، بينعرض للمستخدم).
 */
class ProcessWalletRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
