<?php

namespace Database\Seeders;

use App\Core\Enums\NotificationChannel;
use App\Core\Enums\NotificationType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $inApp     = [NotificationChannel::InApp->value];
        $standard  = [NotificationChannel::InApp->value, NotificationChannel::Push->value];
        $important = [NotificationChannel::InApp->value, NotificationChannel::Push->value, NotificationChannel::Email->value];

        $templates = [
            NotificationType::AppointmentConfirmed->value => [
                'title_en' => 'Appointment Confirmed',
                'title_ar' => 'تم تأكيد الموعد',
                'body_en'  => 'Your appointment with {{doctor_name}} on {{appointment_date}} has been confirmed.',
                'body_ar'  => 'تم تأكيد موعدك مع {{doctor_name}} في {{appointment_date}}.',
                'channels' => $important,
            ],
            NotificationType::AppointmentCancelled->value => [
                'title_en' => 'Appointment Cancelled',
                'title_ar' => 'تم إلغاء الموعد',
                'body_en'  => 'Your appointment with {{doctor_name}} on {{appointment_date}} has been cancelled.',
                'body_ar'  => 'تم إلغاء موعدك مع {{doctor_name}} في {{appointment_date}}.',
                'channels' => $important,
            ],
            NotificationType::AppointmentReminder->value => [
                'title_en' => 'Appointment Reminder',
                'title_ar' => 'تذكير بموعدك',
                'body_en'  => 'Reminder: you have an appointment with {{doctor_name}} at {{appointment_time}}.',
                'body_ar'  => 'تذكير: لديك موعد مع {{doctor_name}} في {{appointment_time}}.',
                'channels' => $standard,
            ],
            NotificationType::AppointmentRescheduled->value => [
                'title_en' => 'Appointment Rescheduled',
                'title_ar' => 'تمت إعادة جدولة الموعد',
                'body_en'  => 'Your appointment with {{doctor_name}} has been rescheduled to {{new_date}}.',
                'body_ar'  => 'تمت إعادة جدولة موعدك مع {{doctor_name}} إلى {{new_date}}.',
                'channels' => $important,
            ],
            NotificationType::AppointmentCompleted->value => [
                'title_en' => 'Appointment Completed',
                'title_ar' => 'اكتمل الموعد',
                'body_en'  => 'Your appointment with {{doctor_name}} has been marked as completed.',
                'body_ar'  => 'تم تسجيل موعدك مع {{doctor_name}} كمكتمل.',
                'channels' => $inApp,
            ],
            NotificationType::ConsultationStarted->value => [
                'title_en' => 'Consultation Started',
                'title_ar' => 'بدأت الاستشارة',
                'body_en'  => '{{doctor_name}} has started the consultation. Tap to join the chat.',
                'body_ar'  => 'بدأ {{doctor_name}} الاستشارة. اضغط للانضمام للمحادثة.',
                'channels' => $standard,
            ],
            NotificationType::ConsultationMessageReceived->value => [
                'title_en' => 'New Message',
                'title_ar' => 'رسالة جديدة',
                'body_en'  => 'You have a new message from {{sender_name}}.',
                'body_ar'  => 'لديك رسالة جديدة من {{sender_name}}.',
                'channels' => $standard,
            ],
            NotificationType::ConsultationEnded->value => [
                'title_en' => 'Consultation Ended',
                'title_ar' => 'انتهت الاستشارة',
                'body_en'  => 'Your consultation with {{doctor_name}} has ended.',
                'body_ar'  => 'انتهت استشارتك مع {{doctor_name}}.',
                'channels' => $inApp,
            ],
            NotificationType::PaymentConfirmed->value => [
                'title_en' => 'Payment Confirmed',
                'title_ar' => 'تم تأكيد الدفع',
                'body_en'  => 'Your payment of {{amount}} has been received successfully.',
                'body_ar'  => 'تم استلام دفعتك بقيمة {{amount}} بنجاح.',
                'channels' => $important,
            ],
            NotificationType::PaymentFailed->value => [
                'title_en' => 'Payment Failed',
                'title_ar' => 'فشلت عملية الدفع',
                'body_en'  => 'Your payment of {{amount}} could not be processed. Please try again.',
                'body_ar'  => 'تعذَّرت عملية الدفع بقيمة {{amount}}. يرجى المحاولة مجدداً.',
                'channels' => $important,
            ],
            NotificationType::InvoiceIssued->value => [
                'title_en' => 'New Invoice',
                'title_ar' => 'فاتورة جديدة',
                'body_en'  => 'A new invoice of {{amount}} has been issued for your appointment.',
                'body_ar'  => 'تم إصدار فاتورة جديدة بقيمة {{amount}} لموعدك.',
                'channels' => $standard,
            ],
            NotificationType::AccessGranted->value => [
                'title_en' => 'Access Granted',
                'title_ar' => 'تم منح صلاحية الوصول',
                'body_en'  => '{{patient_name}} has granted you access to their medical record.',
                'body_ar'  => 'منحك {{patient_name}} صلاحية الوصول إلى سجله الطبي.',
                'channels' => $standard,
            ],
            NotificationType::AccessRevoked->value => [
                'title_en' => 'Access Revoked',
                'title_ar' => 'تم سحب صلاحية الوصول',
                'body_en'  => '{{patient_name}} has revoked your access to their medical record.',
                'body_ar'  => 'سحب {{patient_name}} صلاحية وصولك إلى سجله الطبي.',
                'channels' => $standard,
            ],
            NotificationType::DoctorVerified->value => [
                'title_en' => 'Account Verified',
                'title_ar' => 'تم توثيق حسابك',
                'body_en'  => 'Congratulations! Your doctor account has been verified. You can now receive appointments.',
                'body_ar'  => 'تهانينا! تم توثيق حسابك كطبيب. يمكنك الآن استقبال المواعيد.',
                'channels' => $important,
            ],
            NotificationType::DoctorRejected->value => [
                'title_en' => 'Verification Rejected',
                'title_ar' => 'تم رفض طلب التوثيق',
                'body_en'  => 'Your doctor verification request was rejected. Reason: {{reason}}.',
                'body_ar'  => 'تم رفض طلب توثيق حسابك. السبب: {{reason}}.',
                'channels' => $important,
            ],
            NotificationType::ReportReceived->value => [
                'title_en' => 'New Report Received',
                'title_ar' => 'تم استلام شكوى جديدة',
                'body_en'  => 'A new report has been submitted regarding {{doctor_name}}.',
                'body_ar'  => 'تم تقديم شكوى جديدة بخصوص {{doctor_name}}.',
                'channels' => $inApp,
            ],
            NotificationType::SystemAlert->value => [
                'title_en' => 'System Alert',
                'title_ar' => 'تنبيه من النظام',
                'body_en'  => '{{message}}',
                'body_ar'  => '{{message}}',
                'channels' => $important,
            ],
            NotificationType::RefundRequested->value => [
                'title_en' => 'Refund Request Submitted',
                'title_ar' => 'تم تقديم طلب استرداد',
                'body_en'  => 'Your refund request of {{amount}} has been submitted and is under review.',
                'body_ar'  => 'تم تقديم طلب استرداد بقيمة {{amount}} وهو قيد المراجعة.',
                'channels' => $standard,
            ],
            NotificationType::RefundCompleted->value => [
                'title_en' => 'Refund Completed',
                'title_ar' => 'تم تنفيذ الاسترداد',
                'body_en'  => 'Your refund of {{amount}} has been processed. Reference: {{transaction_reference}}.',
                'body_ar'  => 'تم تنفيذ استرداد بقيمة {{amount}}. المرجع: {{transaction_reference}}.',
                'channels' => $important,
            ],
        ];

        foreach ($templates as $type => $template) {
            DB::table('notification_templates')->insert([
                'type'       => $type,
                'title_en'   => $template['title_en'],
                'title_ar'   => $template['title_ar'],
                'body_en'    => $template['body_en'],
                'body_ar'    => $template['body_ar'],
                'channels'   => json_encode($template['channels']),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ Notification templates seeded (' . count($templates) . ' templates).');
    }
}
