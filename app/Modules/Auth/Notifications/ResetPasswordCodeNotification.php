<?php

namespace App\Modules\Auth\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordCodeNotification extends Notification
{
    public function __construct(
        private readonly string $code
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim($notifiable->first_name ?? '');

        return (new MailMessage)
            ->subject('Your reset password code')
            ->greeting($name !== '' ? "Hello {$name}," : 'Hello,')
            ->line('Use the code below to reset your password:')
            ->line("**{$this->code}**")
            ->line('This code expires in 10 minutes and can only be used once.')
            ->line('If you did not request this code, you can safely ignore this email.');
    }
}
