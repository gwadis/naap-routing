<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

class SmsChannel
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send the given notification via SMS.
     * Optional emergency adapter. If SMS is unconfigured or disabled, exits cleanly without failing.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return array|null
     */
    public function send($notifiable, Notification $notification): ?array
    {
        // If SMS is disabled or unconfigured, exit gracefully with standard unavailable response
        if (!$this->smsService->isConfigured()) {
            return [
                'success' => false,
                'status' => 'SMS_UNAVAILABLE',
                'info' => 'SMS unavailable — no SMS provider configured.',
            ];
        }

        if (!method_exists($notification, 'toSms')) {
            return null;
        }

        $phone = $notifiable->routeNotificationFor('sms', $notification) 
            ?? $notifiable->phone 
            ?? null;

        if (empty($phone)) {
            return [
                'success' => false,
                'status' => 'SMS_UNAVAILABLE',
                'info' => 'SMS unavailable — recipient has no phone number configured.',
            ];
        }

        $message = $notification->toSms($notifiable);

        if (is_array($message)) {
            $message = $message['message'] ?? $message['body'] ?? json_encode($message);
        }

        return $this->smsService->send($phone, (string) $message);
    }
}
