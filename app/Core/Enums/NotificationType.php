<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;


enum NotificationType: string
{
    use EnumValues;

    case AppointmentConfirmed         = 'appointment_confirmed';
    case AppointmentCancelled         = 'appointment_cancelled';
    case AppointmentReminder          = 'appointment_reminder';
    case AppointmentRescheduled       = 'appointment_rescheduled';
    case AppointmentCompleted         = 'appointment_completed';
    case ConsultationStarted          = 'consultation_started';
    case ConsultationMessageReceived  = 'consultation_message_received';
    case ConsultationEnded            = 'consultation_ended';
    case PaymentConfirmed             = 'payment_confirmed';
    case PaymentFailed                = 'payment_failed';
    case InvoiceIssued                = 'invoice_issued';
    case AccessGranted                = 'access_granted';
    case AccessRevoked                = 'access_revoked';
    case DoctorVerified               = 'doctor_verified';
    case DoctorRejected               = 'doctor_rejected';
    case ReportReceived               = 'report_received';
    case SystemAlert                  = 'system_alert';
    case RefundRequested              = 'refund_requested';
    case RefundCompleted              = 'refund_completed';
}
