<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

/**
 * أنواع حركات المحفظة (Wallet Ledger) - كل حركة على أي محفظة (مريض/طبيب/
 * المنصة) لازم تكون من أحد هالأنواع، تستخدم لتفسير سبب كل قيد بسجل
 * WalletTransaction (راجع WalletService::credit()/debit()).
 */
enum WalletTransactionType: string
{
    use EnumValues;

    case TopUp          = 'top_up';           // شحن رصيد المريض (بعد موافقة الأدمن)
    case Withdrawal      = 'withdrawal';        // سحب رصيد الطبيب (بعد موافقة الأدمن)
    case AppointmentCharge = 'appointment_charge'; // خصم من محفظة المريض (دفع كامل أو عربون) + إيداعه بمحفظة المنصة كأمانة
    case AppointmentRefund = 'appointment_refund'; // استرجاع للمريض عند الإلغاء
    case DoctorEarning    = 'doctor_earning';    // تحويل نصيب الطبيب من محفظة المنصة لمحفظته (بعد اكتمال/عدم حضور)
    case PlatformFee      = 'platform_fee';      // عمولة المنصة الثابتة (٥٪) - تسجيلية فقط، المبلغ أصلاً محتجز بمحفظة المنصة
    case AdminAdjustment  = 'admin_adjustment';  // تعديل يدوي من الأدمن (تصحيح خطأ ونحوه)
}
