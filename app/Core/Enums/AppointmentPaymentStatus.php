<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum AppointmentPaymentStatus: string
{
    use EnumValues;

    case Pending     = 'pending';       // لسا ما انعمل أي دفع
    case DepositPaid = 'deposit_paid';  // العربون فقط انعمل (كاش+عربون)
    case FullyPaid   = 'fully_paid';    // كامل الأجرة انعملت أونلاين
    case Settled      = 'settled';       // الموعد اكتمل - نصيب الطبيب اتحول من محفظة المنصة لمحفظته
    case Refunded     = 'refunded';      // الموعد انلغى - المريض استرجع (بالكامل أو ناقص عمولة المنصة)
    case Forfeited     = 'forfeited';     // المريض ما حضر (no-show) - الطبيب احتفظ بالمبلغ المدفوع (ناقص عمولة المنصة)
}
