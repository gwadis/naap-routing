<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use App\Services\EmailService;

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
        $mailData = $notification->toBrevoMail($notifiable);

        $emailService = new EmailService();
        $emailService->send($notifiable->email, $mailData['subject'], $mailData['body']);
    }
}
