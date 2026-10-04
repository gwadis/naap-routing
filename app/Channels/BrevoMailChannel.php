<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use App\Services\EmailService;
use Illuminate\Support\Facades\Log;

class BrevoMailChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $to = null;
        if (method_exists($notifiable, 'routeNotificationForMail')) {
            $to = $notifiable->routeNotificationForMail($notification);
        }
        if (empty($to) && method_exists($notifiable, 'routeNotificationFor')) {
            $to = $notifiable->routeNotificationFor('mail', $notification)
                ?: $notifiable->routeNotificationFor(self::class, $notification);
        }
        if (empty($to)) {
            $to = $notifiable->email ?? null;
        }

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::channel('email')->warning("BrevoMailChannel: No valid recipient email address for notifiable.");
            return;
        }

        if (method_exists($notification, 'toBrevoMail')) {
            $mailData = $notification->toBrevoMail($notifiable);
        } elseif (method_exists($notification, 'toMail')) {
            $mailMessage = $notification->toMail($notifiable);
            $mailData = [
                'subject' => $mailMessage->subject,
                'body' => $mailMessage->render(),
            ];
        } else {
            Log::channel('email')->warning("BrevoMailChannel: Notification has neither toBrevoMail nor toMail methods.");
            return;
        }

        $emailService = new EmailService();
        $emailService->send($to, $mailData['subject'], $mailData['body']);
    }
}
