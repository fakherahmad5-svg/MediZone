<?php

namespace App\Core\Services;

use App\Core\Enums\NotificationStatus;
use App\Core\Enums\NotificationType;
use App\Models\DeviceToken;
use App\Models\Notification as NotificationModel;
use App\Models\NotificationTemplate;
use App\Models\User;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;


class NotificationService extends BaseService
{
    public function __construct(private readonly Messaging $messaging)
    {
    }

    /**
     * @param array<string, mixed> $data متغيرات الاستبدال {{key}} + أي سياق إضافي يُحفَظ في data JSON
     */
    public function notify(User $user, NotificationType $type, array $data = []): ?NotificationModel
    {
        $template = NotificationTemplate::where('type', $type->value)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            $this->logError("No active notification template for type [{$type->value}].");

            return null;
        }

        $locale = $this->resolveLocale($user);

        $notification = NotificationModel::create([
            'user_id' => $user->id,
            'type'    => $type->value,
            'status'  => NotificationStatus::Pending->value,
            'title'   => $this->interpolate($locale === 'ar' ? $template->title_ar : $template->title_en, $data),
            'body'    => $this->interpolate($locale === 'ar' ? $template->body_ar : $template->body_en, $data),
            'data'    => $data,
        ]);

        $this->deliverViaPush($notification, $user);

        return $notification->fresh();
    }

    public function markAsRead(NotificationModel $notification): NotificationModel
    {
        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $notification;
    }

    public function markAllAsRead(User $user): int
    {
        return NotificationModel::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * ⚠️ افتراض غير مؤكَّد — انظر توثيق الصنف أعلاه.
     */
    private function resolveLocale(User $user): string
    {
        return $user->locale ?? 'ar';
    }

    private function interpolate(?string $text, array $data): string
    {
        if ($text === null) {
            return '';
        }

        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $text = str_replace('{{' . $key . '}}', (string) $value, $text);
            }
        }

        return $text;
    }

    /**
     * القناة الوحيدة المُفعَّلة. لا مستخدَم بلا جهاز مسجَّل → لا فشل،
     * فقط يبقى الإشعار sent داخل التطبيق (in_app) بلا push فعلي.
     */
    private function deliverViaPush(NotificationModel $notification, User $user): void
    {
        $tokens = DeviceToken::where('user_id', $user->id)->pluck('token')->all();

        if (empty($tokens)) {
            $notification->update([
                'status'        => NotificationStatus::Sent->value,
                'channels_sent' => ['in_app'],
                'sent_at'       => now(),
            ]);

            return;
        }

        try {
            $message = CloudMessage::new()
                ->withNotification(FirebaseNotification::create($notification->title, $notification->body))
                ->withData($this->stringifyData($notification->data ?? []));

            $report = $this->messaging->sendMulticast($message, $tokens);

            $this->cleanupInvalidTokens($report);

            if ($report->successes()->count() === 0) {
                $notification->update([
                    'status'        => NotificationStatus::Failed->value,
                    'channels_sent' => ['in_app'],
                    'failed_reason' => 'All push deliveries failed.',
                ]);

                return;
            }

            $notification->update([
                'status'        => NotificationStatus::Sent->value,
                'channels_sent' => ['in_app', 'push'],
                'sent_at'       => now(),
            ]);
        } catch (\Throwable $e) {
            $this->logError('FCM push delivery failed: ' . $e->getMessage());

            $notification->update([
                'status'        => NotificationStatus::Failed->value,
                'channels_sent' => ['in_app'],
                'failed_reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * تنظيف تلقائي: توكنات منتهية/غير صالحة (تطبيق حُذف، جهاز أُلغي
     * تسجيله من Firebase) تُحذَف من device_tokens فور اكتشافها —
     * تمنع محاولات إرسال فاشلة متكررة لنفس التوكن الميت.
     */
    private function cleanupInvalidTokens(MulticastSendReport $report): void
    {
        foreach ($report->invalidTokens() as $invalidToken) {
            DeviceToken::where('token', $invalidToken)->delete();
        }
    }

    private function stringifyData(array $data): array
    {
        return array_map(
            fn ($value) => is_scalar($value) ? (string) $value : json_encode($value),
            $data
        );
    }
}
