<?php
namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'type' => 'appointment_confirmed',
                'title_en' => 'Appointment Confirmed',
                'title_ar' => 'تم تأكيد الموعد',
                'body_en' => 'Your appointment with Dr. {{doctor_name}} on {{date}} has been confirmed.',
                'body_ar' => 'تم تأكيد موعدك مع د. {{doctor_name}} بتاريخ {{date}}.',
            ],
            [
                'type' => 'appointment_cancelled',
                'title_en' => 'Appointment Cancelled',
                'title_ar' => 'تم إلغاء الموعد',
                'body_en' => 'Your appointment on {{date}} has been cancelled.',
                'body_ar' => 'تم إلغاء موعدك بتاريخ {{date}}.',
            ],
            [
                'type' => 'appointment_reminder',
                'title_en' => 'Appointment Reminder',
                'title_ar' => 'تذكير بالموعد',
                'body_en' => 'Reminder: you have an appointment tomorrow at {{time}}.',
                'body_ar' => 'تذكير: لديك موعد غداً الساعة {{time}}.',
            ],
            [
                'type' => 'appointment_rescheduled',
                'title_en' => 'Appointment Rescheduled',
                'title_ar' => 'تم إعادة جدولة الموعد',
                'body_en' => 'Your appointment has been rescheduled to {{date}} at {{time}}.',
                'body_ar' => 'تم إعادة جدولة موعدك إلى {{date}} الساعة {{time}}.',
            ],
            [
                'type' => 'appointment_completed',
                'title_en' => 'Appointment Completed',
                'title_ar' => 'اكتمل الموعد',
                'body_en' => 'Your appointment with Dr. {{doctor_name}} has been completed.',
                'body_ar' => 'اكتمل موعدك مع د. {{doctor_name}}.',
            ],
            [
                'type' => 'consultation_started',
                'title_en' => 'Consultation Started',
                'title_ar' => 'بدأت الاستشارة',
                'body_en' => 'Dr. {{doctor_name}} has started your consultation.',
                'body_ar' => 'بدأ د. {{doctor_name}} استشارتك.',
            ],
            [
                'type' => 'consultation_message_received',
                'title_en' => 'New Message',
                'title_ar' => 'رسالة جديدة',
                'body_en' => 'You have a new message in your consultation.',
                'body_ar' => 'لديك رسالة جديدة في الاستشارة.',
            ],
            [
                'type' => 'consultation_ended',
                'title_en' => 'Consultation Ended',
                'title_ar' => 'انتهت الاستشارة',
                'body_en' => 'Your consultation with Dr. {{doctor_name}} has ended.',
                'body_ar' => 'انتهت استشارتك مع د. {{doctor_name}}.',
            ],
            [
                'type' => 'payment_confirmed',
                'title_en' => 'Payment Confirmed',
                'title_ar' => 'تم تأكيد الدفع',
                'body_en' => 'Your payment of {{amount}} SAR has been confirmed.',
                'body_ar' => 'تم تأكيد دفعتك بمبلغ {{amount}} ريال.',
            ],
            [
                'type' => 'payment_failed',
                'title_en' => 'Payment Failed',
                'title_ar' => 'فشل الدفع',
                'body_en' => 'Your payment could not be processed. Please try again.',
                'body_ar' => 'تعذر معالجة الدفع. يرجى المحاولة مرة أخرى.',
            ],
            [
                'type' => 'invoice_issued',
                'title_en' => 'Invoice Issued',
                'title_ar' => 'تم إصدار فاتورة',
                'body_en' => 'A new invoice of {{amount}} SAR has been issued.',
                'body_ar' => 'تم إصدار فاتورة جديدة بمبلغ {{amount}} ريال.',
            ],
            [
                'type' => 'access_granted',
                'title_en' => 'Access Granted',
                'title_ar' => 'تم منح صلاحية الوصول',
                'body_en' => 'Dr. {{doctor_name}} has been granted access to your medical record.',
                'body_ar' => 'تم منح د. {{doctor_name}} صلاحية الوصول لسجلك الطبي.',
            ],
            [
                'type' => 'access_revoked',
                'title_en' => 'Access Revoked',
                'title_ar' => 'تم سحب صلاحية الوصول',
                'body_en' => 'Dr. {{doctor_name}} access to your medical record has been revoked.',
                'body_ar' => 'تم سحب صلاحية د. {{doctor_name}} للوصول لسجلك الطبي.',
            ],
            [
                'type' => 'doctor_verified',
                'title_en' => 'Doctor Verified',
                'title_ar' => 'تم توثيق الطبيب',
                'body_en' => 'Your doctor account has been verified. You can now accept appointments.',
                'body_ar' => 'تم توثيق حسابك كطبيب. يمكنك الآن قبول المواعيد.',
            ],
            [
                'type' => 'doctor_rejected',
                'title_en' => 'Doctor Verification Rejected',
                'title_ar' => 'تم رفض توثيق الطبيب',
                'body_en' => 'Your doctor verification request was rejected. Contact support for details.',
                'body_ar' => 'تم رفض طلب توثيق حسابك كطبيب. تواصل مع الدعم للتفاصيل.',
            ],
            [
                'type' => 'report_received',
                'title_en' => 'Report Received',
                'title_ar' => 'تم استلام بلاغ',
                'body_en' => 'Your report has been received and is under review.',
                'body_ar' => 'تم استلام بلاغك وهو قيد المراجعة.',
            ],
            [
                'type' => 'system_alert',
                'title_en' => 'System Alert',
                'title_ar' => 'تنبيه النظام',
                'body_en' => 'Important system notification: {{message}}',
                'body_ar' => 'تنبيه مهم من النظام: {{message}}',
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::query()->updateOrCreate(
                ['type' => $template['type']],
                [
                    ...$template,
                    'channels' => ['in_app', 'email'],
                    'is_active' => true,
                ]
            );
        }
    }
}
