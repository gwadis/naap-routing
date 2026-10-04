<?php

namespace App\Services\SmsProviders;

use App\Services\SmsProviderInterface;

class NoneSmsProvider implements SmsProviderInterface
{
    /**
     * Provider implementation when SMS is unconfigured or disabled.
     * Never fakes delivery, never reports SMS sent.
     */
    public function send(string $to, string $message): array
    {
        return [
            'success' => false,
            'provider' => 'none',
            'status' => 'SMS_UNAVAILABLE',
            'message_id' => null,
            'info' => 'SMS unavailable — no SMS provider configured.',
            'raw' => null,
        ];
    }
}
