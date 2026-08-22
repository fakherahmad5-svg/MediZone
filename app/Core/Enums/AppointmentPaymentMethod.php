<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum AppointmentPaymentMethod: string
{
    use EnumValues;

    case FullOnline     = 'full_online';   // دفع كامل الأجرة أونلاين عبر المحفظة
    case CashWithDeposit = 'cash_deposit';  // كاش بالعيادة + عربون أونلاين (نسبته متغيرة حسب سجل الغياب)
}
