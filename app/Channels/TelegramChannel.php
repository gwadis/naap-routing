<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

class TelegramChannel
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Send the given notification via Telegram.
     * Only executed if user has explicitly connected Telegram.
     */
    public function send($notifiable, Notification $notification): ?array
    {
        if (!method_exists($notifiable, 'isTelegramConnected') || !$notifiable->isTelegramConnected()) {
            return null;
        }

        if (!$this->telegramService->isConfigured()) {
            return null;
        }

        if (method_exists($notification, 'toTelegram')) {
            try {
                $payload = $notification->toTelegram($notifiable);
                if (empty($payload)) {
                    return null;
                }

                if (is_string($payload)) {
                    return $this->telegramService->sendMessage($notifiable->telegram_chat_id, $payload);
                }

                if (is_array($payload)) {
                    $text = $payload['text'] ?? $payload['message'] ?? '';
                    $markup = $payload['reply_markup'] ?? null;
                    return $this->telegramService->sendMessage($notifiable->telegram_chat_id, $text, $markup);
                }
            } catch (\Throwable $e) {
                Log::warning("[TelegramChannel] Notification dispatch error: " . $e->getMessage());
            }
        }

        return null;
    }
}
